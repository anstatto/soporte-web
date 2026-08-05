<?php

namespace App\Services;

use App\Models\Conversacion;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class CallService
{
    public const TTL = 7200;

    public function __construct(
        private LiveKitTokenService $livekit,
    ) {}

    public function find(string $callId): ?array
    {
        $data = Cache::get($this->key($callId));

        return is_array($data) ? $data : null;
    }

    public function assertParticipant(array $call, User $user): void
    {
        $uid = (int) $user->id;
        if ($uid !== (int) $call['caller_id'] && $uid !== (int) $call['callee_id']) {
            abort(403, 'No participas en esta llamada.');
        }
    }

    public function start(User $caller, User $callee, bool $video = false, ?array $context = null): array
    {
        if (! $this->livekit->enabled()) {
            throw new RuntimeException('Las llamadas no están disponibles. Configura LiveKit Cloud (plan gratis Build).');
        }

        // Modo económico: forzar audio si el admin desactivó video
        if ($video && ! \App\Support\LiveKitConfig::allowVideo()) {
            $video = false;
        }

        if ((int) $caller->id === (int) $callee->id) {
            abort(422, 'No puedes llamarte a ti mismo.');
        }

        if (! $callee->is_active) {
            abort(422, 'El usuario no está activo.');
        }

        $this->authorizeContext($caller, $callee, $context);

        if ($busy = $this->userCallId($caller->id)) {
            $existing = $this->find($busy);
            if ($existing && in_array($existing['status'], ['ringing', 'active'], true)) {
                abort(409, 'Ya tienes una llamada en curso.');
            }
        }
        if ($busy = $this->userCallId($callee->id)) {
            $existing = $this->find($busy);
            if ($existing && in_array($existing['status'], ['ringing', 'active'], true)) {
                abort(409, 'El usuario está en otra llamada.');
            }
        }

        $id = (string) Str::uuid();
        $room = 'call_'.str_replace('-', '', $id);

        $call = [
            'id' => $id,
            'room' => $room,
            'caller_id' => (int) $caller->id,
            'callee_id' => (int) $callee->id,
            'caller_name' => $caller->name,
            'callee_name' => $callee->name,
            'video' => (bool) $video,
            'status' => 'ringing',
            'context' => $context,
            'created_at' => now()->toIso8601String(),
        ];

        $this->store($call);
        Cache::put($this->userKey($caller->id), $id, self::TTL);
        Cache::put($this->userKey($callee->id), $id, self::TTL);

        return $call;
    }

    public function accept(array $call, User $user): array
    {
        $this->assertParticipant($call, $user);

        if ((int) $user->id !== (int) $call['callee_id']) {
            abort(403, 'Solo el destinatario puede aceptar.');
        }

        if ($call['status'] !== 'ringing') {
            abort(409, 'La llamada ya no está sonando.');
        }

        $call['status'] = 'active';
        $call['accepted_at'] = now()->toIso8601String();
        $this->store($call);

        return $call;
    }

    public function decline(array $call, User $user): array
    {
        $this->assertParticipant($call, $user);

        if ($call['status'] !== 'ringing') {
            abort(409, 'La llamada ya no está sonando.');
        }

        $call['status'] = 'declined';
        $call['ended_at'] = now()->toIso8601String();
        $this->store($call);
        $this->clearUsers($call);

        return $call;
    }

    public function end(array $call, User $user): array
    {
        $this->assertParticipant($call, $user);

        if (in_array($call['status'], ['ended', 'declined', 'missed'], true)) {
            return $call;
        }

        $call['status'] = 'ended';
        $call['ended_at'] = now()->toIso8601String();
        $this->store($call);
        $this->clearUsers($call);

        return $call;
    }

    /** Timeout de ringing (no contestó). */
    public function miss(array $call, User $user): array
    {
        $this->assertParticipant($call, $user);

        if ($call['status'] !== 'ringing') {
            return $call;
        }

        $call['status'] = 'missed';
        $call['ended_at'] = now()->toIso8601String();
        $this->store($call);
        $this->clearUsers($call);

        return $call;
    }

    public function ringTimeoutSeconds(): int
    {
        return \App\Support\LiveKitConfig::ringTimeout();
    }

    public function tokenFor(array $call, User $user): string
    {
        $this->assertParticipant($call, $user);

        if (! in_array($call['status'], ['ringing', 'active'], true)) {
            abort(409, 'La llamada ya terminó.');
        }

        // El llamante puede unirse en ringing (espera); el callee solo tras accept (active)
        if ((int) $user->id === (int) $call['callee_id'] && $call['status'] === 'ringing') {
            abort(409, 'Acepta la llamada primero.');
        }

        return $this->livekit->createToken(
            identity: (string) $user->id,
            name: $user->name,
            room: $call['room'],
        );
    }

    public function publicPayload(array $call, ?User $forUser = null): array
    {
        $payload = [
            'id' => $call['id'],
            'room' => $call['room'],
            'caller_id' => $call['caller_id'],
            'callee_id' => $call['callee_id'],
            'caller_name' => $call['caller_name'],
            'callee_name' => $call['callee_name'],
            'video' => (bool) $call['video'],
            'status' => $call['status'],
            'context' => $call['context'] ?? null,
            'url' => \App\Support\LiveKitConfig::url(),
        ];

        if ($forUser) {
            $payload['peer_name'] = (int) $forUser->id === (int) $call['caller_id']
                ? $call['callee_name']
                : $call['caller_name'];
            $payload['is_caller'] = (int) $forUser->id === (int) $call['caller_id'];
        }

        return $payload;
    }

    private function authorizeContext(User $caller, User $callee, ?array $context): void
    {
        $type = $context['type'] ?? null;
        $id = isset($context['id']) ? (int) $context['id'] : null;

        if ($type === 'dm' && $id) {
            $conv = Conversacion::query()->findOrFail($id);
            abort_unless($caller->can('view', $conv) && $callee->can('view', $conv), 403);

            return;
        }

        if ($type === 'ticket' && $id) {
            $ticket = Ticket::query()->findOrFail($id);
            abort_unless($caller->can('view', $ticket) && $callee->can('view', $ticket), 403);

            return;
        }

        // Sin contexto: mismo workspace
        $ws = $caller->current_workspace_id;
        abort_unless($ws && $callee->workspaces()->where('workspaces.id', $ws)->exists(), 403);
    }

    private function store(array $call): void
    {
        Cache::put($this->key($call['id']), $call, self::TTL);
    }

    private function clearUsers(array $call): void
    {
        Cache::forget($this->userKey($call['caller_id']));
        Cache::forget($this->userKey($call['callee_id']));
    }

    private function userCallId(int $userId): ?string
    {
        $id = Cache::get($this->userKey($userId));

        return is_string($id) ? $id : null;
    }

    private function key(string $callId): string
    {
        return 'livekit_call:'.$callId;
    }

    private function userKey(int $userId): string
    {
        return 'livekit_call_user:'.$userId;
    }
}
