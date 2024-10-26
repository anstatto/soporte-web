<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomResetPasswordNotification;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Role; // Agrega esta línea para importar la clase Role

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password', // Si permites cambiar la contraseña, maneja esto con cuidado
        'departamento_id',
        'username',
        // Agrega otros campos que desees permitir que el usuario edite
    ];

    /**
     * Los atributos que deben estar ocultos para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Obtener los atributos que deben ser convertidos a tipos nativos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relación con los tickets.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Relación con el departamento.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * Relación con los comentarios.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comentarios()
    {
        return $this->hasMany(Comentario::class);
    }

    /**
     * Obtener el número de tickets abiertos.
     *
     * @return int
     */
    public function getTicketsAbiertos()
    {
        return $this->tickets()->whereHas('estado', function ($query) {
            $query->where('nombre', '!=', 'Cerrado');
        })->count();
    }

    /**
     * Determinar si el usuario es administrador.
     *
     * @return bool
     */
    public function esAdministrador()
    {
        return $this->hasRole('admin');
    }

    /**
     * Enviar notificación de restablecimiento de contraseña.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
