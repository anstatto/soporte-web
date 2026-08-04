<?php

namespace App\Models;

use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'departamento_id',
        'current_workspace_id',
        'username',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function tickets()
    {
        return $this->belongsToMany(Ticket::class);
    }

    public function createdTickets()
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function comentarios()
    {
        return $this->hasMany(Comentario::class);
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('is_admin')
            ->withTimestamps();
    }

    public function currentWorkspace()
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function conversaciones()
    {
        return $this->belongsToMany(Conversacion::class, 'conversacion_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function getTicketsAbiertos()
    {
        return $this->tickets()->whereHas('estado', function ($query) {
            $query->where('nombre', '!=', 'Cerrado');
        })->count();
    }

    public function esAdministrador(): bool
    {
        return $this->hasRole('admin');
    }

    /** Agente de soporte: roles admin/soporte o cualquier rol marcado is_agent. */
    public function esSoporte(): bool
    {
        if ($this->hasRole(['admin', 'soporte'])) {
            return true;
        }

        return $this->roles->contains(fn ($r) => (bool) ($r->is_agent ?? false));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }
}
