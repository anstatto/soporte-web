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
        'prioridad',
        'position',
        'user_id',
        'workspace_id',
        'fecha_entrega',
        'recordatorio',
    ];

    protected $with = ['user', 'departamento', 'estado'];

    protected $casts = [
        'fecha_entrega' => 'datetime',
        'recordatorio' => 'datetime',
        'position' => 'integer',
    ];

    public const PRIORIDADES = [
        'baja' => ['label' => 'Baja', 'emoji' => '', 'color' => '#3D7A5F'],
        'media' => ['label' => 'Media', 'emoji' => '', 'color' => '#2F6FAD'],
        'alta' => ['label' => 'Alta', 'emoji' => '', 'color' => '#B7791F'],
        'urgente' => ['label' => 'Urgente', 'emoji' => '', 'color' => '#C4554D'],
    ];

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

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function etiquetas()
    {
        return $this->belongsToMany(Etiqueta::class);
    }

    public function adjuntos()
    {
        return $this->hasMany(TicketAdjunto::class);
    }
}
