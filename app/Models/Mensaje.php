<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Mensaje extends Model
{
    protected $table = 'mensajes';

    protected $fillable = [
        'conversacion_id',
        'user_id',
        'contenido',
        'path',
        'nombre_original',
        'mime',
        'kind',
        'size',
    ];

    protected $appends = ['url'];

    protected $casts = [
        'size' => 'integer',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }

    public function toPayload(?int $viewerId = null): array
    {
        return [
            'id' => $this->id,
            'contenido' => $this->contenido,
            'path' => $this->path,
            'nombre' => $this->nombre_original,
            'mime' => $this->mime,
            'kind' => $this->kind,
            'size' => $this->size,
            'url' => $this->url,
            'user_id' => $this->user_id,
            'user' => $this->relationLoaded('user')
                ? $this->user?->only(['id', 'name', 'username'])
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'mine' => $viewerId !== null && (int) $this->user_id === (int) $viewerId,
        ];
    }
}
