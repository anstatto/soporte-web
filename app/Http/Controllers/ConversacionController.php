<?php

namespace App\Http\Controllers;

use App\Events\MensajeDmCreado;
use App\Events\UsuarioEscribiendo;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\TicketAdjunto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConversacionController extends Controller
{
    public function open(Request $request)
    {
        $this->authorize('viewAny', Conversacion::class);

        $user = Auth::user();
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $otherId = (int) $validated['user_id'];
        abort_if($otherId === (int) $user->id, 422, 'No puedes chatear contigo mismo.');

        $other = User::where('is_active', true)->findOrFail($otherId);
        $wsId = $user->current_workspace_id;

        abort_unless($wsId && $this->sameWorkspace($user, $other, $wsId), 403);

        $conversacion = $this->findOrCreateDm($user->id, $other->id, $wsId);

        return redirect()->route('tickets.index', [
            'tab' => 'personas',
            'dm' => $conversacion->id,
        ]);
    }

    public function show(Conversacion $conversacion)
    {
        $this->authorize('view', $conversacion);

        $user = Auth::user();
        $conversacion->load(['users:id,name,username', 'mensajes.user:id,name,username']);

        $this->markRead($conversacion, $user->id);

        return response()->json([
            'conversacion' => $this->payload($conversacion, $user),
        ]);
    }

    public function storeMensaje(Request $request, Conversacion $conversacion)
    {
        $this->authorize('message', $conversacion);

        $request->validate([
            'contenido' => 'nullable|string|max:5000',
            'archivo' => 'nullable|file|max:15360|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,webm,ogg,mp3,m4a,wav,aac,mpeg,mpga,opus',
        ]);

        $contenido = trim((string) $request->input('contenido', ''));
        $file = $request->file('archivo');

        if ($contenido === '' && ! $file) {
            return response()->json(['message' => 'Escribe un mensaje o adjunta un archivo.'], 422);
        }

        $path = null;
        $nombre = null;
        $mime = null;
        $kind = null;
        $size = 0;

        if ($file) {
            $path = $file->store('dms/'.$conversacion->id, 'public');
            $nombre = $file->getClientOriginalName();
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            $kind = TicketAdjunto::kindFromMime($mime, $nombre);
            $size = $file->getSize() ?: 0;
        }

        $mensaje = $conversacion->mensajes()->create([
            'user_id' => Auth::id(),
            'contenido' => $contenido !== '' ? $contenido : null,
            'path' => $path,
            'nombre_original' => $nombre,
            'mime' => $mime,
            'kind' => $kind,
            'size' => $size,
        ]);

        $conversacion->touch();
        $this->clearTyping($conversacion->id, Auth::id());
        $mensaje->load('user:id,name,username');

        broadcast(new MensajeDmCreado($conversacion, $mensaje))->toOthers();

        return response()->json([
            'mensaje' => $mensaje->toPayload(Auth::id()),
        ]);
    }

    public function poll(Request $request, Conversacion $conversacion)
    {
        $this->authorize('view', $conversacion);

        $after = (int) $request->input('after', 0);
        $mensajes = $conversacion->mensajes()
            ->with('user:id,name,username')
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->orderBy('id')
            ->get()
            ->map(fn (Mensaje $m) => $m->toPayload(Auth::id()))
            ->values();

        return response()->json([
            'mensajes' => $mensajes,
            'typing' => $this->typingList($conversacion->id, Auth::id()),
        ]);
    }

    public function typing(Conversacion $conversacion)
    {
        $this->authorize('message', $conversacion);

        $uid = Auth::id();
        $key = "dm_typing_{$conversacion->id}";
        $list = Cache::get($key, []);
        $list[$uid] = [
            'id' => $uid,
            'name' => Auth::user()->name,
            'at' => time(),
        ];
        $cutoff = time() - 8;
        $list = array_filter($list, fn ($row) => ($row['at'] ?? 0) >= $cutoff);
        Cache::put($key, $list, now()->addSeconds(30));

        broadcast(new UsuarioEscribiendo(
            'conversacion.'.$conversacion->id,
            $uid,
            Auth::user()->name
        ))->toOthers();

        return response()->json(['ok' => true]);
    }

    protected function findOrCreateDm(int $a, int $b, int $workspaceId): Conversacion
    {
        $existingId = DB::table('conversacion_user as cu1')
            ->join('conversacion_user as cu2', 'cu1.conversacion_id', '=', 'cu2.conversacion_id')
            ->join('conversaciones', 'conversaciones.id', '=', 'cu1.conversacion_id')
            ->where('conversaciones.type', 'dm')
            ->where('conversaciones.workspace_id', $workspaceId)
            ->where('cu1.user_id', $a)
            ->where('cu2.user_id', $b)
            ->value('cu1.conversacion_id');

        if ($existingId) {
            return Conversacion::findOrFail($existingId);
        }

        return DB::transaction(function () use ($a, $b, $workspaceId) {
            $c = Conversacion::create([
                'workspace_id' => $workspaceId,
                'type' => 'dm',
            ]);
            $c->users()->attach([$a, $b]);

            return $c;
        });
    }

    protected function sameWorkspace(User $a, User $b, int $workspaceId): bool
    {
        if ($a->hasRole('admin')) {
            return $b->workspaces()->where('workspaces.id', $workspaceId)->exists()
                || (int) $b->current_workspace_id === $workspaceId;
        }

        $aOk = $a->workspaces()->where('workspaces.id', $workspaceId)->exists()
            || (int) $a->current_workspace_id === $workspaceId;
        $bOk = $b->workspaces()->where('workspaces.id', $workspaceId)->exists()
            || (int) $b->current_workspace_id === $workspaceId;

        return $aOk && $bOk;
    }

    public function payload(Conversacion $conversacion, User $viewer): array
    {
        $conversacion->loadMissing(['users:id,name,username', 'mensajes.user:id,name,username']);
        $peer = $conversacion->peerFor($viewer);

        return [
            'id' => $conversacion->id,
            'type' => 'dm',
            'peer' => $peer?->only(['id', 'name', 'username']),
            'participantes' => $conversacion->users->map->only(['id', 'name', 'username'])->values(),
            'mensajes' => $conversacion->mensajes->map(fn (Mensaje $m) => $m->toPayload($viewer->id))->values(),
            'canMessage' => $viewer->can('message', $conversacion),
            'updated_at' => $conversacion->updated_at?->toIso8601String(),
        ];
    }

    public function listForUser(User $user): array
    {
        $wsId = $user->current_workspace_id;

        $rows = Conversacion::query()
            ->where('type', 'dm')
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->with(['users:id,name,username', 'latestMensaje.user:id,name,username'])
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get();

        $metas = \App\Support\ChatInbox::dmMetas($user, $rows);

        return $rows->map(function (Conversacion $c) use ($user, $metas) {
            $peer = $c->peerFor($user);
            $last = $c->latestMensaje;
            $pack = $metas[$c->id] ?? ['unread' => 0, 'meta' => []];

            return [
                'id' => $c->id,
                'peer' => $peer?->only(['id', 'name', 'username']),
                'last_message' => $last ? [
                    'preview' => $last->contenido
                        ? \Illuminate\Support\Str::limit($last->contenido, 72)
                        : match ($last->kind) {
                            'audio' => '🎤 Nota de voz',
                            'image' => '📷 Imagen',
                            'pdf' => '📄 PDF',
                            'word' => '📄 Documento',
                            default => ($last->nombre_original ?: 'Archivo'),
                        },
                    'by' => $last->user?->name,
                    'created_at' => $last->created_at?->toIso8601String(),
                ] : null,
                'updated_at' => $c->updated_at?->toIso8601String(),
                'unread' => (int) ($pack['unread'] ?? 0),
                'meta' => $pack['meta'] ?? [],
            ];
        })
            ->sortByDesc(fn ($row) => ($row['meta']['pinned'] ?? false) ? 1 : 0)
            ->values()
            ->all();
    }

    public function directoryForUser(User $user): array
    {
        $wsId = $user->current_workspace_id;
        if (! $wsId) {
            return [];
        }

        $query = User::query()
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->orderBy('name');

        if ($user->hasRole('admin')) {
            // admin ve usuarios del área actual
            $query->where(function ($q) use ($wsId) {
                $q->where('current_workspace_id', $wsId)
                    ->orWhereHas('workspaces', fn ($w) => $w->where('workspaces.id', $wsId));
            });
        } else {
            $query->whereHas('workspaces', fn ($w) => $w->where('workspaces.id', $wsId));
        }

        return $query->get(['id', 'name', 'username'])->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
        ])->values()->all();
    }

    protected function markRead(Conversacion $conversacion, int $userId): void
    {
        $conversacion->users()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);
    }

    protected function clearTyping(int $conversacionId, int $userId): void
    {
        $key = "dm_typing_{$conversacionId}";
        $list = Cache::get($key, []);
        unset($list[$userId]);
        Cache::put($key, $list, now()->addSeconds(30));
    }

    protected function typingList(int $conversacionId, int $me): array
    {
        $list = Cache::get("dm_typing_{$conversacionId}", []);
        $cutoff = time() - 5;

        return collect($list)
            ->filter(fn ($row, $uid) => (int) $uid !== (int) $me && ($row['at'] ?? 0) >= $cutoff)
            ->values()
            ->map(fn ($row) => ['id' => $row['id'], 'name' => $row['name']])
            ->all();
    }
}
