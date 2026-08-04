<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ChatState extends Model
{
    public const TYPE_TICKET = 'ticket';

    public const TYPE_DM = 'dm';

    protected $fillable = [
        'user_id',
        'chat_type',
        'chat_id',
        'last_read_at',
        'marked_unread',
        'pinned_at',
        'muted_at',
        'archived_at',
        'starred_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'marked_unread' => 'boolean',
        'pinned_at' => 'datetime',
        'muted_at' => 'datetime',
        'archived_at' => 'datetime',
        'starred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function for(User $user, string $type, int $chatId): self
    {
        return static::firstOrCreate(
            [
                'user_id' => $user->id,
                'chat_type' => $type,
                'chat_id' => $chatId,
            ],
            [
                'last_read_at' => null,
                'marked_unread' => false,
            ]
        );
    }

    public function markRead(?Carbon $at = null): void
    {
        $this->forceFill([
            'last_read_at' => $at ?? now(),
            'marked_unread' => false,
        ])->save();
    }

    public function markUnread(): void
    {
        $this->forceFill(['marked_unread' => true])->save();
    }

    public function toMeta(): array
    {
        return [
            'last_read_at' => $this->last_read_at?->toIso8601String(),
            'marked_unread' => (bool) $this->marked_unread,
            'pinned' => (bool) $this->pinned_at,
            'muted' => (bool) $this->muted_at,
            'archived' => (bool) $this->archived_at,
            'starred' => (bool) $this->starred_at, // leer más tarde
            'pinned_at' => $this->pinned_at?->toIso8601String(),
            'starred_at' => $this->starred_at?->toIso8601String(),
        ];
    }
}
