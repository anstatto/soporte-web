<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Departamento; // Asegúrate de importar el modelo de Departamento
use App\Models\User; // Asegúrate de importar el modelo de User

class UserController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $departamentos = Departamento::all(); // Obtener todos los departamentos
        return view('perfil.show', compact('user', 'departamentos'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        // Asegúrate de que $user sea una instancia de User
        if ($user instanceof User) {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id,
                'username' => 'required|string|max:255|unique:users,username,' . $user->id,
                'departamento_id' => 'required|exists:departamentos,id',
            ]);

            $user->update($validatedData);

            return redirect()->route('perfil.show')->with('success', 'Perfil actualizado exitosamente.');
        }

        return redirect()->route('perfil.show')->with('error', 'No se pudo actualizar el perfil.');
    }
}
