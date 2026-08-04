<?php

namespace App\Http\Controllers;

use App\Models\Comentario;
use App\Models\Estado;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();

        // Solicitante entra al portal (chat + reportar); soporte al tablero
        if ($user->hasRole('solicitante') && ! $user->hasRole(['admin', 'soporte'])) {
            return redirect()->route('portal');
        }

        if ($user->can('view tickets')) {
            return redirect()->route('tickets.board');
        }

        $isSoporte = $user->esSoporte();

        $scoped = function () use ($user, $isSoporte) {
            $q = Ticket::query();
            if (! $isSoporte) {
                $q->where(function ($qq) use ($user) {
                    $qq->where('user_id', $user->id)
                        ->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id));
                });
            }

            return $q;
        };

        $ids = (clone $scoped())->pluck('id');

        $byEstado = Estado::query()
            ->withCount(['tickets as total' => fn ($q) => $q->whereIn('id', $ids)])
            ->orderBy('nombre')
            ->get()
            ->map(fn ($e) => [
                'estado' => $e->nombre,
                'color' => $e->color ?? '#1E4E79',
                'total' => (int) $e->total,
            ]);

        $byDepartamento = (clone $scoped())
            ->join('departamentos', 'departamentos.id', '=', 'tickets.departamento_id')
            ->select('departamentos.nombre as departamento', DB::raw('count(tickets.id) as total'))
            ->groupBy('departamentos.nombre')
            ->orderBy('departamentos.nombre')
            ->get()
            ->map(fn ($row) => [
                'departamento' => $row->departamento ?? 'Sin depto',
                'total' => (int) $row->total,
            ]);

        $trend = (clone $scoped())
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        $recent = (clone $scoped())
            ->with(['user:id,name', 'estado:id,nombre,color', 'departamento:id,nombre'])
            ->withCount('comentarios')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (Ticket $t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'user' => $t->user?->name,
                'estado' => $t->estado?->nombre,
                'estado_color' => $t->estado?->color,
                'departamento' => $t->departamento?->nombre,
                'comentarios_count' => $t->comentarios_count ?? 0,
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        $kpis = [
            'total' => $ids->count(),
            'mios' => Ticket::where('user_id', $user->id)->count(),
            'comentarios' => Comentario::where('user_id', $user->id)->count(),
            'abiertos' => (clone $scoped())->whereHas('estado', fn ($q) => $q->where('nombre', '!=', 'Cerrado'))->count(),
        ];

        return Inertia::render('Dashboard', [
            'kpis' => $kpis,
            'byEstado' => $byEstado,
            'byDepartamento' => $byDepartamento,
            'trend' => $trend,
            'recent' => $recent,
            'isSoporte' => $isSoporte,
        ]);
    }
}
