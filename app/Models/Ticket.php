<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'descripcion',
        'departamento_id',
        'estado_id',
        'user_id',
        'fecha_entrega', // Nuevo campo
        'recordatorio' // Nuevo campo
    ];

    protected $with = ['user', 'departamento', 'estado'];
    protected $dates = ['fecha_entrega', 'recordatorio'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function comentarios()
    {
        return $this->hasMany(Comentario::class);
    }

    public function getEstadoActualAttribute()
    {
        return $this->estado->nombre;
    }

    public function getDepartamentoAsignadoAttribute()
    {
        return $this->departamento->nombre;
    }

    public function getUsuarioCreadorAttribute()
    {
        return $this->user->name;
    }

    public function getTiempoAbiertoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getEstaAbiertoAttribute()
    {
        return $this->estado->nombre !== 'Cerrado';
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
