<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Departamento; // Asegúrate de importar el modelo de Departamento
use App\Models\User; // Asegúrate de importar el modelo de User
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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

    public function showAssignRoles()
    {
        $users = User::with('roles')->get(); // Obtener usuarios con sus roles
        $roles = Role::all();
        $permissions = Permission::all();
        return view('users.assign_roles', compact('users', 'roles', 'permissions'));
    }

    public function assignRoles(Request $request)
    {
        // Validar que se envíen los datos necesarios
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name', // Asegúrate de que los roles existan
        ]);

        $user = User::find($request->user_id);
        if ($user) {
            $user->syncRoles($request->roles); // Sincronizar roles
            return redirect()->back()->with('success', 'Roles asignados correctamente.');
        }
        return redirect()->back()->with('error', 'Usuario no encontrado.');
    }
}
