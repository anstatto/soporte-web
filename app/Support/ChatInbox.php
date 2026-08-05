<?php

namespace App\Support;

use App\Models\ChatState;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatInbox
{
    /**
     * @param  array<int, int>  $ticketIds
     * @return array<int, array{unread:int,meta:array}>
     */
    public static function ticketMetas(User $user, array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $states = ChatState::query()
            ->where('user_id', $user->id)
            ->where('chat_type', ChatState::TYPE_TICKET)
            ->whereIn('chat_id', $ticketIds)
            ->get()
            ->keyBy('chat_id');

        $lastMsgAt = DB::table('comentarios')
            ->select('ticket_id', DB::raw('MAX(created_at) as last_at'), DB::raw('MAX(id) as last_id'))
            ->whereIn('ticket_id', $ticketIds)
            ->groupBy('ticket_id')
            ->get()
            ->keyBy('ticket_id');

        $lastAuthors = DB::table('comentarios')
            ->select('ticket_id', 'user_id', 'id')
            ->whereIn('id', $lastMsgAt->pluck('last_id')->filter()->all())
            ->get()
            ->keyBy('ticket_id');

        $out = [];
        foreach ($ticketIds as $id) {
            $state = $states->get($id);
            $meta = $state?->toMeta() ?? [
                'last_read_at' => null,
                'marked_unread' => false,
                'pinned' => false,
                'muted' => false,
                'archived' => false,
                'starred' => false,
                'pinned_at' => null,
                'starred_at' => null,
            ];

            $lastAt = $lastMsgAt->get($id)?->last_at;
            $lastAuthor = (int) ($lastAuthors->get($id)?->user_id ?? 0);
            $readAt = $state?->last_read_at?->toDateTimeString();
            $unread = self::computeUnread(
                $meta,
                $lastAt,
                $lastAuthor,
                (int) $user->id,
                $readAt
            );

            if ($unread > 0 && ! empty($meta['marked_unread'])) {
                $unread = 1;
            } elseif ($unread > 0) {
                $q = DB::table('comentarios')
                    ->where('ticket_id', $id)
                    ->where('user_id', '!=', $user->id);
                if ($readAt) {
                    $q->where('created_at', '>', $readAt);
                }
                $unread = max(1, (int) $q->count());
            }

            $out[(int) $id] = [
                'unread' => $unread,
                'meta' => $meta,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, Conversacion>  $rows
     * @return array<int, array{unread:int,meta:array}>
     */
    public static function dmMetas(User $user, Collection $rows): array
    {
        $ids = $rows->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $states = ChatState::query()
            ->where('user_id', $user->id)
            ->where('chat_type', ChatState::TYPE_DM)
            ->whereIn('chat_id', $ids)
            ->get()
            ->keyBy('chat_id');

        $out = [];
        foreach ($rows as $c) {
            $state = $states->get($c->id);
            $meta = $state?->toMeta() ?? [
                'last_read_at' => null,
                'marked_unread' => false,
                'pinned' => false,
                'muted' => false,
                'archived' => false,
                'starred' => false,
                'pinned_at' => null,
                'starred_at' => null,
            ];

            // Prefer pivot last_read if newer
            $pivotRead = $c->users->firstWhere('id', $user->id)?->pivot?->last_read_at;
            $readAt = $state?->last_read_at?->toDateTimeString() ?: $pivotRead;

            $last = $c->latestMensaje;
            $unread = self::computeUnread(
                $meta,
                $last?->created_at?->toDateTimeString(),
                (int) ($last?->user_id ?? 0),
                (int) $user->id,
                $readAt
            );

            // Count messages after last_read for badge number
            if ($unread > 0 && $readAt && $last) {
                $unread = Mensaje::query()
                    ->where('conversacion_id', $c->id)
                    ->where('user_id', '!=', $user->id)
                    ->where('created_at', '>', $readAt)
                    ->count() ?: 1;
            } elseif ($unread > 0 && ! $readAt) {
                $unread = Mensaje::query()
                    ->where('conversacion_id', $c->id)
                    ->where('user_id', '!=', $user->id)
                    ->count() ?: 1;
            }

            $out[(int) $c->id] = [
                'unread' => $unread,
                'meta' => $meta,
            ];
        }

        return $out;
    }

    protected static function computeUnread(array $meta, ?string $lastAt, int $lastAuthorId, int $viewerId, ?string $readAt): int
    {
        if (! empty($meta['marked_unread'])) {
            return 1;
        }
        if (! $lastAt) {
            return 0;
        }
        if ($lastAuthorId === $viewerId) {
            return 0;
        }
        if (! $readAt) {
            return 1;
        }

        return strtotime($lastAt) > strtotime($readAt) ? 1 : 0;
    }

    public static function markTicketRead(User $user, int $ticketId): void
    {
        ChatState::for($user, ChatState::TYPE_TICKET, $ticketId)->markRead();
        self::forgetUnreadCache($user);
    }

    public static function markDmRead(User $user, int $conversacionId): void
    {
        ChatState::for($user, ChatState::TYPE_DM, $conversacionId)->markRead();
        Conversacion::find($conversacionId)
            ?->users()
            ->updateExistingPivot($user->id, ['last_read_at' => now()]);
        self::forgetUnreadCache($user);
    }

    public static function forgetUnreadCache(User $user): void
    {
        \Illuminate\Support\Facades\Cache::forget('unread_chats.'.$user->id);
    }

    /**
     * Cantidad de chats (tickets + DM) con mensajes sin leer.
     * Cache corto para no pesarlo en cada request Inertia.
     */
    public static function unreadChatsCount(User $user): int
    {
        return (int) \Illuminate\Support\Facades\Cache::remember(
            'unread_chats.'.$user->id,
            20,
            function () use ($user) {
                $base = Ticket::query()
                    ->when($user->current_workspace_id, fn ($q) => $q->where('workspace_id', $user->current_workspace_id))
                    ->when(! $user->esSoporte(), function ($q) use ($user) {
                        $q->where(function ($qq) use ($user) {
                            $qq->where('user_id', $user->id)
                                ->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id));
                        });
                    });

                $recentIds = (clone $base)
                    ->whereHas('comentarios')
                    ->withMax('comentarios', 'created_at')
                    ->orderByDesc('comentarios_max_created_at')
                    ->limit(150)
                    ->pluck('id')
                    ->all();

                $markedIds = ChatState::query()
                    ->where('user_id', $user->id)
                    ->where('chat_type', ChatState::TYPE_TICKET)
                    ->where('marked_unread', true)
                    ->pluck('chat_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $ticketIds = array_values(array_unique(array_merge($recentIds, $markedIds)));

                $ticketUnread = 0;
                if ($ticketIds !== []) {
                    foreach (self::ticketMetas($user, $ticketIds) as $pack) {
                        if ((int) ($pack['unread'] ?? 0) > 0) {
                            $ticketUnread++;
                        }
                    }
                }

                $dmUnread = 0;
                if ($user->can('chat with users')) {
                    $dms = Conversacion::query()
                        ->whereHas('users', fn ($u) => $u->where('users.id', $user->id))
                        ->with(['users', 'latestMensaje'])
                        ->limit(100)
                        ->get();
                    foreach (self::dmMetas($user, $dms) as $pack) {
                        if ((int) ($pack['unread'] ?? 0) > 0) {
                            $dmUnread++;
                        }
                    }
                }

                return $ticketUnread + $dmUnread;
            }
        );
    }
}
