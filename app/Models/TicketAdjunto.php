<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketAdjunto extends Model
{
    protected $table = 'ticket_adjuntos';

    protected $fillable = [
        'ticket_id',
        'comentario_id',
        'user_id',
        'path',
        'nombre_original',
        'mime',
        'size',
        'kind',
    ];

    protected $appends = ['url'];

    protected $casts = [
        'size' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function comentario(): BelongsTo
    {
        return $this->belongsTo(Comentario::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public static function kindFromMime(?string $mime, ?string $filename = null): string
    {
        $mime = strtolower((string) $mime);
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

        if (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return 'image';
        }
        if (
            str_starts_with($mime, 'audio/')
            || $mime === 'video/webm' // MediaRecorder a veces marca audio/webm como video/webm
            || in_array($ext, ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'wav', 'aac', 'opus', 'mpeg', 'mpga'], true)
        ) {
            return 'audio';
        }
        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return 'pdf';
        }
        if (
            in_array($mime, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ], true)
            || in_array($ext, ['doc', 'docx'], true)
        ) {
            return 'word';
        }

        return 'other';
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre_original,
            'mime' => $this->mime,
            'size' => $this->size,
            'kind' => $this->kind,
            'url' => $this->url,
            'comentario_id' => $this->comentario_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
