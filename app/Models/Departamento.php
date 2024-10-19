<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Departamento extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public static function getDepartamentosDisponibles()
    {
        return self::pluck('nombre', 'id');
    }

    public function getTicketsAbiertos()
    {
        return $this->tickets()->whereHas('estado', function ($query) {
            $query->where('nombre', '!=', 'Cerrado');
        })->count();
    }
}
