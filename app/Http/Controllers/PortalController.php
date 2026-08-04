<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->can('view tickets'), 403);

        $isSoporte = $user->esSoporte();

        $base = Ticket::query()->with([
            'estado:id,nombre,color',
            'departamento:id,nombre',
            'user:id,name',
            'users:id,name',
        ])->withCount('comentarios')
            ->withMax('comentarios', 'created_at');

        if ($user->current_workspace_id) {
            $base->where('workspace_id', $user->current_workspace_id);
        }

        if (! $isSoporte) {
            $base->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id));
            });
        }

        $conversations = (clone $base)
            ->latest('updated_at')
            ->take(25)
            ->get()
            ->map(fn (Ticket $t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'estado' => $t->estado?->only(['id', 'nombre', 'color']),
                'departamento' => $t->departamento?->only(['id', 'nombre']),
                'user' => $t->user?->only(['id', 'name']),
                'asignados' => $t->users->map->only(['id', 'name']),
                'sin_asignar' => $t->users->isEmpty(),
                'comentarios_count' => $t->comentarios_count,
                'last_activity' => $t->comentarios_max_created_at ?? $t->updated_at?->toIso8601String(),
            ]);

        $estados = Estado::orderBy('id')->get(['id', 'nombre', 'color']);

        $flowCounts = $estados->map(function (Estado $e) use ($base) {
            return [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'color' => $e->color,
                'total' => (clone $base)->where('estado_id', $e->id)->count(),
            ];
        });

        $unassigned = $isSoporte
            ? Ticket::query()
                ->whereDoesntHave('users')
                ->whereHas('estado', fn ($q) => $q->where('nombre', '!=', 'Cerrado'))
                ->count()
            : 0;

        return Inertia::render('Portal/Index', [
            'conversations' => $conversations,
            'flow' => $flowCounts,
            'unassignedCount' => $unassigned,
            'isSoporte' => $isSoporte,
        ]);
    }
}
