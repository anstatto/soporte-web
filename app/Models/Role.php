<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name']; // Asegúrate de que 'name' sea un atributo en tu tabla de roles

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
