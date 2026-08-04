<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function index()
    {
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $workspaces = Workspace::withCount('users', 'tickets')
            ->with(['users:id,name,email,username'])
            ->orderBy('name')
            ->get()
            ->map(fn (Workspace $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'slug' => $w->slug,
                'description' => $w->description,
                'is_active' => $w->is_active,
                'users_count' => $w->users_count,
                'tickets_count' => $w->tickets_count,
                'user_ids' => $w->users->pluck('id'),
                'users' => $w->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'is_admin' => (bool) $u->pivot->is_admin,
                ]),
            ]);

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
            'allUsers' => User::orderBy('name')->get(['id', 'name', 'username', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $ws = Workspace::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::random(4),
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        $ids = collect($data['user_ids'] ?? [])->push(Auth::id())->unique();
        $sync = [];
        foreach ($ids as $uid) {
            $sync[$uid] = ['is_admin' => (int) $uid === (int) Auth::id()];
        }
        $ws->users()->sync($sync);

        return redirect()->route('workspaces.index')->with('success', 'Área de trabajo creada.');
    }

    public function update(Request $request, Workspace $workspace)
    {
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $workspace->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? $workspace->is_active,
        ]);

        if ($request->has('user_ids')) {
            $sync = [];
            foreach ($data['user_ids'] as $uid) {
                $existing = $workspace->users()->where('users.id', $uid)->first();
                $sync[$uid] = ['is_admin' => (bool) ($existing?->pivot?->is_admin)];
            }
            // Mantener al menos un admin: el actual si está
            if (! collect($sync)->contains(fn ($p) => $p['is_admin'])) {
                $sync[Auth::id()] = ['is_admin' => true];
            }
            $workspace->users()->sync($sync);
        }

        return redirect()->route('workspaces.index')->with('success', 'Área de trabajo actualizada.');
    }

    public function destroy(Workspace $workspace)
    {
        abort_unless(Auth::user()->hasRole('admin'), 403);

        if (Workspace::count() <= 1) {
            return back()->with('error', 'Debe existir al menos un área de trabajo.');
        }

        $fallback = Workspace::where('id', '!=', $workspace->id)->first();
        User::where('current_workspace_id', $workspace->id)
            ->update(['current_workspace_id' => $fallback?->id]);
        $workspace->tickets()->update(['workspace_id' => $fallback?->id]);
        $workspace->delete();

        return redirect()->route('workspaces.index')->with('success', 'Área de trabajo eliminada.');
    }

    public function switch(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
        ]);

        $user = Auth::user();
        $ws = Workspace::findOrFail($data['workspace_id']);

        abort_unless(
            $user->hasRole('admin') || $user->workspaces()->where('workspaces.id', $ws->id)->exists(),
            403
        );

        $user->update(['current_workspace_id' => $ws->id]);

        return back()->with('success', 'Cambiaste a «'.$ws->name.'».');
    }
}
