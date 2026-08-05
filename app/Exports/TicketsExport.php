<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TicketsExport
{
    protected Collection $tickets;

    protected string $tipoReporte;

    private const ESTADOS_CERRADOS = ['cerrado', 'resuelto', 'finalizado', 'completado'];

    public function __construct(Collection $tickets, $tipoReporte)
    {
        $this->tickets = $tickets;
        $this->tipoReporte = $tipoReporte;
    }

    public function export($fileName)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $exportMethod = 'exportar'.ucfirst($this->tipoReporte);
        if (! method_exists($this, $exportMethod)) {
            throw new \Exception('Tipo de reporte no soportado');
        }

        $this->$exportMethod($sheet, $headerStyle);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function exportarBasico($sheet, $headerStyle): void
    {
        $headers = ['ID', 'Usuario', 'Título', 'Estado', 'Prioridad', 'Fecha de creación'];
        $this->setHeaders($sheet, $headers, $headerStyle);

        $row = 2;
        foreach ($this->tickets as $ticket) {
            $sheet->fromArray([
                $ticket->id,
                $ticket->user?->name,
                $ticket->titulo,
                $ticket->estado?->nombre,
                $ticket->prioridad,
                optional($ticket->created_at)->format('Y-m-d H:i:s'),
            ], null, 'A'.$row);
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'F');
    }

    private function exportarDetallado($sheet, $headerStyle): void
    {
        $headers = ['ID', 'Usuario', 'Título', 'Descripción', 'Departamento', 'Estado', 'Prioridad', 'Fecha de creación', 'Última actualización'];
        $this->setHeaders($sheet, $headers, $headerStyle);

        $row = 2;
        foreach ($this->tickets as $ticket) {
            $sheet->fromArray([
                $ticket->id,
                $ticket->user?->name,
                $ticket->titulo,
                strip_tags($ticket->descripcion ?? ''),
                $ticket->departamento?->nombre,
                $ticket->estado?->nombre,
                $ticket->prioridad,
                optional($ticket->created_at)->format('Y-m-d H:i:s'),
                optional($ticket->updated_at)->format('Y-m-d H:i:s'),
            ], null, 'A'.$row);
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'I');
    }

    private function exportarEstadistico($sheet, $headerStyle): void
    {
        $headers = ['Estado', 'Cantidad', 'Porcentaje'];
        $this->setHeaders($sheet, $headers, $headerStyle);

        $ticketsPorEstado = $this->tickets->groupBy(fn ($t) => $t->estado?->nombre ?? 'Sin estado');
        $totalTickets = max($this->tickets->count(), 1);
        $row = 2;

        foreach ($ticketsPorEstado as $estado => $tickets) {
            $cantidad = $tickets->count();
            $porcentaje = ($cantidad / $totalTickets) * 100;
            $sheet->fromArray([
                $estado,
                $cantidad,
                number_format($porcentaje, 2).'%',
            ], null, 'A'.$row);
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'C');
    }

    private function exportarRendimiento($sheet, $headerStyle): void
    {
        $headers = ['ID', 'Usuario', 'Título', 'Prioridad', 'Estado', 'Horas resolución', 'Creado', 'Actualizado'];
        $this->setHeaders($sheet, $headers, $headerStyle);

        $row = 2;
        foreach ($this->tickets as $ticket) {
            $horas = $this->horasResolucion($ticket);
            $sheet->fromArray([
                $ticket->id,
                $ticket->user?->name,
                $ticket->titulo,
                $ticket->prioridad,
                $ticket->estado?->nombre,
                $horas !== null ? number_format($horas, 2) : '',
                optional($ticket->created_at)->format('Y-m-d H:i:s'),
                optional($ticket->updated_at)->format('Y-m-d H:i:s'),
            ], null, 'A'.$row);
            $row++;
        }

        // Resumen por usuario
        $row += 2;
        $sheet->fromArray([['Resumen por usuario']], null, 'A'.$row);
        $row++;
        $this->setHeaders($sheet, ['Usuario', 'Total', 'Cerrados', 'Prom. horas', 'Tasa cierre %'], $headerStyle, 'A'.$row);
        $row++;

        foreach ($this->tickets->groupBy('user_id') as $tickets) {
            $cerrados = $tickets->filter(fn ($t) => $this->horasResolucion($t) !== null);
            $prom = $cerrados->avg(fn ($t) => $this->horasResolucion($t));
            $tasa = $tickets->count() > 0 ? ($cerrados->count() / $tickets->count()) * 100 : 0;
            $sheet->fromArray([
                $tickets->first()->user?->name ?? 'Sin usuario',
                $tickets->count(),
                $cerrados->count(),
                $prom !== null ? number_format($prom, 2) : '—',
                number_format($tasa, 1),
            ], null, 'A'.$row);
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'H');
    }

    private function horasResolucion($ticket): ?float
    {
        $nombre = mb_strtolower(trim($ticket->estado?->nombre ?? ''));
        if (! in_array($nombre, self::ESTADOS_CERRADOS, true)) {
            return null;
        }
        if (! $ticket->created_at || ! $ticket->updated_at) {
            return null;
        }

        return round($ticket->created_at->diffInMinutes($ticket->updated_at) / 60, 2);
    }

    private function setHeaders($sheet, array $headers, array $headerStyle, string $startCell = 'A1'): void
    {
        $sheet->fromArray([$headers], null, $startCell);
        $startCol = preg_replace('/\d+/', '', $startCell);
        $startRow = (int) preg_replace('/\D+/', '', $startCell);
        $endCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle($startCol.$startRow.':'.$endCol.$startRow)->applyFromArray($headerStyle);
    }

    private function autoSizeColumns($sheet, string $startColumn, string $endColumn): void
    {
        foreach (range($startColumn, $endColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
