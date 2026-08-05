<?php

namespace App\Http\Controllers;

use App\Events\CallAccepted;
use App\Events\CallDeclined;
use App\Events\CallEnded;
use App\Events\CallIncoming;
use App\Events\CallMissed;
use App\Models\User;
use App\Services\CallService;
use App\Services\LiveKitTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class CallController extends Controller
{
    public function __construct(
        private CallService $calls,
        private LiveKitTokenService $livekit,
    ) {}

    public function status()
    {
        return response()->json([
            'enabled' => $this->livekit->enabled(),
            'ring_timeout' => $this->calls->ringTimeoutSeconds(),
        ]);
    }

    public function start(Request $request)
    {
        abort_unless(Auth::user()->can('use calls') || Auth::user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'callee_id' => 'required|integer|exists:users,id',
            'video' => 'sometimes|boolean',
            'context' => 'nullable|array',
            'context.type' => 'nullable|in:dm,ticket',
            'context.id' => 'nullable|integer',
        ]);

        $caller = Auth::user();
        $callee = User::findOrFail($validated['callee_id']);

        try {
            $call = $this->calls->start(
                $caller,
                $callee,
                (bool) ($validated['video'] ?? false),
                $validated['context'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $public = $this->calls->publicPayload($call, $caller);
        $token = $this->calls->tokenFor($call, $caller);

        event(new CallIncoming(
            (int) $callee->id,
            $this->calls->publicPayload($call, $callee),
        ));

        return response()->json([
            'call' => $public,
            'token' => $token,
        ]);
    }

    public function accept(string $callId)
    {
        abort_unless(Auth::user()->can('use calls') || Auth::user()->hasRole('admin'), 403);

        $user = Auth::user();
        $call = $this->calls->find($callId);
        abort_unless($call, 404, 'Llamada no encontrada.');

        $call = $this->calls->accept($call, $user);
        $token = $this->calls->tokenFor($call, $user);

        event(new CallAccepted(
            (int) $call['caller_id'],
            $this->calls->publicPayload($call),
        ));

        return response()->json([
            'call' => $this->calls->publicPayload($call, $user),
            'token' => $token,
        ]);
    }

    public function decline(string $callId)
    {
        $user = Auth::user();
        $call = $this->calls->find($callId);
        abort_unless($call, 404, 'Llamada no encontrada.');

        $call = $this->calls->decline($call, $user);
        $otherId = (int) $user->id === (int) $call['caller_id']
            ? (int) $call['callee_id']
            : (int) $call['caller_id'];

        event(new CallDeclined($otherId, $this->calls->publicPayload($call)));

        return response()->json(['call' => $this->calls->publicPayload($call, $user)]);
    }

    public function end(string $callId)
    {
        $user = Auth::user();
        $call = $this->calls->find($callId);
        abort_unless($call, 404, 'Llamada no encontrada.');

        $call = $this->calls->end($call, $user);
        $otherId = (int) $user->id === (int) $call['caller_id']
            ? (int) $call['callee_id']
            : (int) $call['caller_id'];

        event(new CallEnded($otherId, $this->calls->publicPayload($call)));

        return response()->json(['call' => $this->calls->publicPayload($call, $user)]);
    }

    public function miss(string $callId)
    {
        $user = Auth::user();
        $call = $this->calls->find($callId);
        abort_unless($call, 404, 'Llamada no encontrada.');

        $wasRinging = ($call['status'] ?? '') === 'ringing';
        $call = $this->calls->miss($call, $user);

        if ($wasRinging && ($call['status'] ?? '') === 'missed') {
            $otherId = (int) $user->id === (int) $call['caller_id']
                ? (int) $call['callee_id']
                : (int) $call['caller_id'];

            event(new CallMissed($otherId, $this->calls->publicPayload($call)));
        }

        return response()->json(['call' => $this->calls->publicPayload($call, $user)]);
    }

    public function token(string $callId)
    {
        $user = Auth::user();
        $call = $this->calls->find($callId);
        abort_unless($call, 404, 'Llamada no encontrada.');

        return response()->json([
            'call' => $this->calls->publicPayload($call, $user),
            'token' => $this->calls->tokenFor($call, $user),
            'url' => $this->livekit->url(),
        ]);
    }
}
