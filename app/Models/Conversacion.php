<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversacion extends Model
{
    protected $table = 'conversaciones';

    protected $fillable = [
        'workspace_id',
        'type',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversacion_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class)->orderBy('id');
    }

    public function latestMensaje(): HasOne
    {
        return $this->hasOne(Mensaje::class)->latestOfMany();
    }

    public function peerFor(User $viewer): ?User
    {
        return $this->users->first(fn (User $u) => (int) $u->id !== (int) $viewer->id);
    }
}
