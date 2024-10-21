<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = ['titulo', 'descripcion', 'departamento_id', 'estado_id', 'user_id'];

    protected $with = ['user', 'departamento', 'estado'];

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
}
