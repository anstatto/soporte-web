<?php

namespace App\Http\Controllers;

use App\Exports\TicketsExport;
use App\Models\Estado;
use App\Models\Ticket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class ReporteController extends Controller
{
    /** Estados considerados resueltos para métricas de rendimiento */
    private const ESTADOS_CERRADOS = ['cerrado', 'resuelto', 'finalizado', 'completado'];

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('view reports'), 403);

        $fechaInicio = $request->input('fecha_inicio')
            ? Carbon::parse($request->input('fecha_inicio'))
            : Carbon::today()->setHour(7)->setMinute(0)->setSecond(0);

        $fechaFin = $request->input('fecha_fin')
            ? Carbon::parse($request->input('fecha_fin'))
            : Carbon::today()->setHour(18)->setMinute(0)->setSecond(0);

        return Inertia::render('Reportes/Index', [
            'users' => User::orderBy('name')->get(['id', 'name']),
            'estados' => Estado::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'tiposReporte' => $this->getTiposReporte(),
            'filters' => [
                'fecha_inicio' => $fechaInicio->format('Y-m-d\TH:i'),
                'fecha_fin' => $fechaFin->format('Y-m-d\TH:i'),
                'user_id' => $request->input('user_id'),
                'estado_id' => $request->input('estado_id'),
                'tipo_reporte' => $request->input('tipo_reporte', 'basico'),
            ],
        ]);
    }

    public function exportar(Request $request)
    {
        abort_unless(
            auth()->user()->can('generate reports') || auth()->user()->can('view reports'),
            403
        );

        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo_reporte' => 'nullable|in:basico,detallado,estadistico,rendimiento',
            'format' => 'nullable|in:pdf,excel,csv',
            'user_id' => 'nullable|integer|exists:users,id',
            'estado_id' => 'nullable|integer|exists:estados,id',
            'preview' => 'nullable|boolean',
        ]);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));
        $tickets = $this->getFilteredTickets($request, $fechaInicio, $fechaFin);
        $format = $request->input('format', 'pdf');
        $tipoReporte = $request->input('tipo_reporte', 'basico');
        $preview = $request->boolean('preview');

        return match ($format) {
            'excel' => (new TicketsExport($tickets->get(), $tipoReporte))->export('reporte_soportes.xlsx'),
            'csv' => $this->exportarCSV($tickets->get(), $tipoReporte),
            default => $this->generarPDF($tickets, $tipoReporte, $fechaInicio, $fechaFin, $preview),
        };
    }

    private function exportarCSV(Collection $tickets, string $tipoReporte)
    {
        $filename = "reporte_{$tipoReporte}_".now()->format('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($tickets, $tipoReporte) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($tipoReporte === 'estadistico') {
                fputcsv($file, ['Estado', 'Cantidad', 'Porcentaje'], ';');
                $total = max($tickets->count(), 1);
                foreach ($tickets->groupBy(fn ($t) => $t->estado?->nombre ?? 'Sin estado') as $estado => $group) {
                    fputcsv($file, [
                        $estado,
                        $group->count(),
                        number_format(($group->count() / $total) * 100, 2).'%',
                    ], ';');
                }
            } elseif ($tipoReporte === 'rendimiento') {
                $enriched = $this->enrichTickets($tickets);
                fputcsv($file, ['ID', 'Título', 'Usuario', 'Prioridad', 'Estado', 'Horas resolución', 'Creado', 'Actualizado'], ';');
                foreach ($enriched as $ticket) {
                    fputcsv($file, [
                        $ticket->id,
                        $ticket->titulo,
                        $ticket->user?->name,
                        $ticket->prioridad,
                        $ticket->estado?->nombre,
                        $ticket->tiempo_resolucion_horas !== null
                            ? number_format($ticket->tiempo_resolucion_horas, 2)
                            : '',
                        optional($ticket->created_at)->format('Y-m-d H:i:s'),
                        optional($ticket->updated_at)->format('Y-m-d H:i:s'),
                    ], ';');
                }
            } elseif ($tipoReporte === 'detallado') {
                fputcsv($file, ['ID', 'Título', 'Descripción', 'Usuario', 'Departamento', 'Estado', 'Prioridad', 'Creado', 'Actualizado'], ';');
                foreach ($tickets as $ticket) {
                    fputcsv($file, [
                        $ticket->id,
                        $ticket->titulo,
                        strip_tags($ticket->descripcion ?? ''),
                        $ticket->user?->name,
                        $ticket->departamento?->nombre,
                        $ticket->estado?->nombre,
                        $ticket->prioridad,
                        optional($ticket->created_at)->format('Y-m-d H:i:s'),
                        optional($ticket->updated_at)->format('Y-m-d H:i:s'),
                    ], ';');
                }
            } else {
                fputcsv($file, ['ID', 'Título', 'Usuario', 'Departamento', 'Estado', 'Prioridad', 'Fecha'], ';');
                foreach ($tickets as $ticket) {
                    fputcsv($file, [
                        $ticket->id,
                        $ticket->titulo,
                        $ticket->user?->name,
                        $ticket->departamento?->nombre,
                        $ticket->estado?->nombre,
                        $ticket->prioridad,
                        optional($ticket->created_at)->format('Y-m-d H:i:s'),
                    ], ';');
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function generarPDF($ticketsQuery, string $tipoReporte, Carbon $fechaInicio, Carbon $fechaFin, bool $preview = false)
    {
        $collection = $this->enrichTickets($ticketsQuery->get());
        $ticketsPorUsuario = $collection->groupBy('user_id');

        $pdf = Pdf::loadView("reportes.print.{$tipoReporte}", [
            'ticketsPorUsuario' => $ticketsPorUsuario,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'appName' => config('app.name', 'Soporte'),
            'reportFooter' => \App\Models\Setting::get('report_footer'),
        ])->setPaper('a4', 'portrait');

        $filename = 'reporte_'.$tipoReporte.'_'.now()->format('Ymd_His').'.pdf';

        if ($preview) {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    private function enrichTickets(Collection $tickets): Collection
    {
        return $tickets->map(function (Ticket $ticket) {
            $nombre = mb_strtolower(trim($ticket->estado?->nombre ?? ''));
            $esCerrado = in_array($nombre, self::ESTADOS_CERRADOS, true);

            $ticket->tiempo_resolucion_horas = null;
            if ($esCerrado && $ticket->created_at && $ticket->updated_at) {
                $ticket->tiempo_resolucion_horas = round(
                    $ticket->created_at->diffInMinutes($ticket->updated_at) / 60,
                    2
                );
            }

            return $ticket;
        });
    }

    private function getFilteredTickets(Request $request, Carbon $fechaInicio, Carbon $fechaFin)
    {
        $user = auth()->user();

        return Ticket::with(['user', 'departamento', 'estado'])
            ->when($user->current_workspace_id, fn ($q) => $q->where('workspace_id', $user->current_workspace_id))
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('estado_id'), fn ($q) => $q->where('estado_id', $request->estado_id))
            ->latest();
    }

    private function getTiposReporte(): array
    {
        return [
            'basico' => 'Reporte básico',
            'detallado' => 'Reporte detallado',
            'estadistico' => 'Reporte estadístico',
            'rendimiento' => 'Reporte de rendimiento',
        ];
    }
}
