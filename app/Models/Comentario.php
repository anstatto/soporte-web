<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Comentario extends Model
{
    use HasFactory;

    protected $fillable = ['contenido', 'imagen', 'user_id', 'ticket_id'];

    protected $appends = ['imagen_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function adjunto()
    {
        return $this->hasOne(TicketAdjunto::class);
    }

    public function getImagenUrlAttribute(): ?string
    {
        if (! $this->imagen) {
            return null;
        }

        return Storage::disk('public')->url($this->imagen);
    }
}
