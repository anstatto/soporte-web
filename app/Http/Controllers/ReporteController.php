<?php

namespace App\Http\Controllers;

use App\Exports\TicketsExport;
use App\Models\Estado;
use App\Models\Ticket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteController extends Controller
{
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

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));
        $tickets = $this->getFilteredTickets($request, $fechaInicio, $fechaFin);
        $format = $request->input('format', 'pdf');
        $tipoReporte = $request->input('tipo_reporte', 'basico');

        return match ($format) {
            'excel' => (new TicketsExport($tickets->get(), $tipoReporte))->export('reporte_soportes.xlsx'),
            'csv' => $this->exportarCSV($tickets->get()),
            default => $this->generarPDF($tickets, $tipoReporte, $fechaInicio, $fechaFin),
        };
    }

    private function exportarCSV($tickets)
    {
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=reporte_soportes.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($tickets) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Título', 'Usuario', 'Departamento', 'Estado', 'Fecha']);

            foreach ($tickets as $ticket) {
                fputcsv($file, [
                    $ticket->id,
                    $ticket->titulo,
                    $ticket->user?->name,
                    $ticket->departamento?->nombre,
                    $ticket->estado?->nombre,
                    optional($ticket->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function generarPDF($tickets, $tipoReporte, $fechaInicio, $fechaFin)
    {
        $ticketsPorUsuario = $tickets->get()->groupBy('user_id');
        $pdf = PDF::loadView("reportes.print.{$tipoReporte}", compact('ticketsPorUsuario', 'fechaInicio', 'fechaFin'));

        return $pdf->download('reporte_soportes.pdf');
    }

    private function getFilteredTickets($request, $fechaInicio, $fechaFin)
    {
        $user = auth()->user();

        return Ticket::with(['user', 'departamento', 'estado'])
            ->when($user->current_workspace_id, fn ($q) => $q->where('workspace_id', $user->current_workspace_id))
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('estado_id'), fn ($q) => $q->where('estado_id', $request->estado_id))
            ->latest();
    }

    private function getTiposReporte()
    {
        return [
            'basico' => 'Reporte Básico',
            'detallado' => 'Reporte Detallado',
            'estadistico' => 'Reporte Estadístico',
            'rendimiento' => 'Reporte de Rendimiento',
        ];
    }
}
