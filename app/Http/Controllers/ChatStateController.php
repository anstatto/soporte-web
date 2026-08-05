<?php

namespace App\Http\Controllers;

use App\Models\ChatState;
use App\Models\Conversacion;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ChatStateController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'chat_type' => ['required', Rule::in([ChatState::TYPE_TICKET, ChatState::TYPE_DM])],
            'chat_id' => 'required|integer',
            'action' => ['required', Rule::in([
                'read',
                'unread',
                'star',
                'unstar',
                'pin',
                'unpin',
                'mute',
                'unmute',
                'archive',
                'unarchive',
            ])],
        ]);

        $user = Auth::user();
        $type = $validated['chat_type'];
        $chatId = (int) $validated['chat_id'];

        $this->authorizeAccess($user, $type, $chatId);

        $state = ChatState::for($user, $type, $chatId);

        match ($validated['action']) {
            'read' => $state->markRead(),
            'unread' => $state->markUnread(),
            'star' => $state->forceFill(['starred_at' => now()])->save(),
            'unstar' => $state->forceFill(['starred_at' => null])->save(),
            'pin' => $state->forceFill(['pinned_at' => now()])->save(),
            'unpin' => $state->forceFill(['pinned_at' => null])->save(),
            'mute' => $state->forceFill(['muted_at' => now()])->save(),
            'unmute' => $state->forceFill(['muted_at' => null])->save(),
            'archive' => $state->forceFill(['archived_at' => now()])->save(),
            'unarchive' => $state->forceFill(['archived_at' => null])->save(),
        };

        $state->refresh();

        // También marca notificaciones del ticket
        if ($type === ChatState::TYPE_TICKET && $validated['action'] === 'read') {
            $user->unreadNotifications
                ->filter(fn ($n) => (int) ($n->data['ticket_id'] ?? 0) === $chatId)
                ->each->markAsRead();
        }

        if ($type === ChatState::TYPE_DM && $validated['action'] === 'read') {
            $conv = Conversacion::find($chatId);
            $conv?->users()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        }

        \App\Support\ChatInbox::forgetUnreadCache($user);

        return response()->json([
            'ok' => true,
            'meta' => $state->toMeta(),
        ]);
    }

    protected function authorizeAccess($user, string $type, int $chatId): void
    {
        if ($type === ChatState::TYPE_TICKET) {
            $ticket = Ticket::findOrFail($chatId);
            abort_unless($user->can('view', $ticket), 403);

            return;
        }

        abort_unless($user->can('chat with users'), 403);
        $conv = Conversacion::findOrFail($chatId);
        abort_unless($user->can('view', $conv), 403);
    }
}
