<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Estado extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'color'];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public static function getEstadosDisponibles()
    {
        return self::pluck('nombre', 'id');
    }
}
