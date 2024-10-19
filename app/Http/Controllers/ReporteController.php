<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use App\Exports\TicketsExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $users = User::all();

        // Establecer fechas por defecto si no se proporcionan
        $fechaInicio = $request->input('fecha_inicio')
            ? Carbon::parse($request->input('fecha_inicio'))
            : Carbon::today()->setHour(7)->setMinute(0)->setSecond(0);

        $fechaFin = $request->input('fecha_fin')
            ? Carbon::parse($request->input('fecha_fin'))
            : Carbon::today()->setHour(18)->setMinute(0)->setSecond(0);

        // Obtener tickets filtrados
        $tickets = $this->getFilteredTickets($request, $fechaInicio, $fechaFin);

        // Agrupar tickets por usuario
        $ticketsPorUsuario = $tickets->groupBy('user_id');

        // Obtener tipos de reporte
        $tiposReporte = $this->getTiposReporte();

        return view('reportes.index', compact('users', 'tickets', 'ticketsPorUsuario', 'tiposReporte', 'fechaInicio', 'fechaFin'));
    }

    public function imprimir(Request $request)
    {
        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));
        $tickets = $this->getFilteredTickets($request, $fechaInicio, $fechaFin);
        $ticketsPorUsuario = $tickets->groupBy('user_id');
        $tipoReporte = $request->input('tipo_reporte', 'basico');

        $view = view("reportes.print.{$tipoReporte}", compact('ticketsPorUsuario', 'fechaInicio', 'fechaFin'))->render();
        return response()->json(['html' => $view]);
    }

    public function exportar(Request $request)
    {
        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));
        $tickets = $this->getFilteredTickets($request, $fechaInicio, $fechaFin);
        $format = $request->input('format', 'pdf');
        $tipoReporte = $request->input('tipo_reporte', 'basico');
        switch ($format) {
            case 'excel':
                $export = new TicketsExport($tickets, $tipoReporte);
                return $export->export('reporte_soportes.xlsx');
            case 'csv':
                return $this->exportarCSV($tickets, $tipoReporte);
            case 'pdf':
            default:
                return $this->generarPDF($tickets, $tipoReporte);
        }
    }

    private function exportarCSV($tickets, $tipoReporte)
    {
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=reporte_soportes.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function() use ($tickets, $tipoReporte) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Título', 'Usuario', 'Departamento', 'Estado', 'Fecha de Creación']);

            foreach ($tickets as $ticket) {
                fputcsv($file, [
                    $ticket->id,
                    $ticket->titulo,
                    $ticket->user->name,
                    $ticket->departamento->nombre,
                    $ticket->estado->nombre,
                    $ticket->created_at->format('Y-m-d H:i:s')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function generarPDF($tickets, $tipoReporte)
    {
        $ticketsPorUsuario = $tickets->groupBy('user_id');
        $pdf = PDF::loadView("reportes.pdf.{$tipoReporte}", compact('ticketsPorUsuario'));
        return $pdf->download('reporte_soportes.pdf');
    }

    private function getFilteredTickets(Request $request, $fechaInicio, $fechaFin)
    {
        $query = Ticket::with(['user', 'estado', 'departamento'])
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->orderBy('user_id')
            ->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return $query->get();
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
