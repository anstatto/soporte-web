<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        return Inertia::render('Roles/Index', [
            'roles' => Role::with('permissions')->orderBy('name')->get()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'is_agent' => (bool) $r->is_agent,
                'permissions' => $r->permissions->pluck('name')->values(),
            ]),
            'permissions' => Permission::orderBy('name')->pluck('name')->values(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
            'is_agent' => 'boolean',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'is_agent' => (bool) $request->boolean('is_agent'),
        ]);
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('roles.index')->with('success', 'Rol creado.');
    }

    public function update(Request $request, $id)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
            'is_agent' => 'boolean',
        ]);

        $role = Role::findOrFail($id);
        if (! in_array($role->name, ['admin', 'soporte', 'solicitante'], true)) {
            $role->name = $request->name;
        }
        $role->is_agent = $request->boolean('is_agent');
        $role->save();
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('roles.index')->with('success', 'Rol actualizado.');
    }

    public function destroy($id)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $role = Role::findOrFail($id);
        if (in_array($role->name, ['admin', 'soporte', 'solicitante'], true)) {
            return back()->with('error', 'No se puede eliminar un rol del sistema.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado.');
    }
}
