<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('view users') || Auth::user()->can('manage users'), 403);

        $users = User::with(['roles', 'departamento:id,nombre', 'workspaces:id'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.$request->search.'%';
                $q->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', $s)
                        ->orWhere('email', 'like', $s)
                        ->orWhere('username', 'like', $s);
                });
            })
            ->when($request->filled('role'), function ($q) use ($request) {
                $q->role($request->input('role'));
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('is_active', (bool) (int) $request->input('status'));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'username' => $u->username,
                'is_active' => $u->is_active,
                'departamento_id' => $u->departamento_id,
                'departamento' => $u->departamento?->nombre,
                'role' => $u->getRoleNames()->first(),
                'roles' => $u->getRoleNames()->values(),
                'workspace_ids' => $u->workspaces->pluck('id')->values(),
            ]);

        $canManage = Auth::user()->can('manage users');

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'status']),
            'roles' => Role::orderBy('name')->pluck('name'),
            'canManage' => $canManage,
            'formCatalog' => $canManage
                ? [
                    'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
                    'roles' => Role::orderBy('name')->get(['id', 'name', 'is_agent']),
                    'workspaces' => Workspace::orderBy('name')->get(['id', 'name']),
                ]
                : null,
            'assignable' => Auth::user()->hasRole('admin')
                ? [
                    'users' => User::with('roles')->orderBy('name')->get()->map(fn (User $u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'username' => $u->username,
                        'roles' => $u->getRoleNames()->values(),
                    ]),
                    'roles' => Role::orderBy('name')->get(['id', 'name', 'is_agent']),
                ]
                : null,
        ]);
    }

    public function create()
    {
        abort_unless(Auth::user()->can('manage users'), 403);

        return redirect()->route('users.index');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('manage users'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'departamento_id' => 'nullable|exists:departamentos,id',
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'password' => 'nullable|string|min:8',
            'workspace_ids' => 'nullable|array',
            'workspace_ids.*' => 'exists:workspaces,id',
        ]);

        $tempPassword = $validated['password'] ?? Str::password(10);
        $wsIds = collect($validated['workspace_ids'] ?? [])->filter()->values();
        if ($wsIds->isEmpty() && Auth::user()->current_workspace_id) {
            $wsIds = collect([Auth::user()->current_workspace_id]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'departamento_id' => $validated['departamento_id'] ?? null,
            'password' => Hash::make($tempPassword),
            'is_active' => true,
            'current_workspace_id' => $wsIds->first(),
        ]);

        $user->syncRoles([$validated['role']]);

        if ($wsIds->isNotEmpty()) {
            $user->workspaces()->sync($wsIds->mapWithKeys(fn ($id) => [$id => ['is_admin' => false]])->all());
        }

        return redirect()
            ->route('users.index')
            ->with('success', "Usuario {$user->name} creado.")
            ->with('temp_password', $tempPassword);
    }

    public function edit(User $user)
    {
        abort_unless(Auth::user()->can('manage users'), 403);

        return redirect()->route('users.index');
    }

    public function updateUser(Request $request, User $user)
    {
        abort_unless(Auth::user()->can('manage users'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'departamento_id' => 'nullable|exists:departamentos,id',
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'is_active' => 'boolean',
            'password' => 'nullable|string|min:8',
            'workspace_ids' => 'nullable|array',
            'workspace_ids.*' => 'exists:workspaces,id',
        ]);

        $user->update([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'departamento_id' => $validated['departamento_id'] ?? null,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

        if (array_key_exists('workspace_ids', $validated)) {
            $wsIds = collect($validated['workspace_ids'] ?? [])->filter()->values();
            $user->workspaces()->sync($wsIds->mapWithKeys(fn ($id) => [$id => ['is_admin' => false]])->all());
            if ($wsIds->isNotEmpty() && ! $wsIds->contains($user->current_workspace_id)) {
                $user->update(['current_workspace_id' => $wsIds->first()]);
            }
        }

        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }

    public function show()
    {
        $user = Auth::user()->load('departamento:id,nombre');

        return Inertia::render('Perfil/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'departamento_id' => $user->departamento_id,
                'departamento' => $user->departamento?->nombre,
                'roles' => $user->getRoleNames(),
            ],
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'departamento_id' => 'nullable|exists:departamentos,id',
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
            'departamento_id' => $validated['departamento_id'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        return back()->with('success', 'Perfil actualizado.');
    }

    public function assignRoles(Request $request)
    {
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->syncRoles($request->roles);

        return redirect()->route('users.index')->with('success', 'Roles asignados.');
    }
}
