<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Database\Eloquent\Collection;

class TicketsExport
{
    protected $tickets;
    protected $tipoReporte;

    public function __construct(Collection $tickets, $tipoReporte)
    {
        $this->tickets = $tickets;
        $this->tipoReporte = $tipoReporte;
    }

    public function export($fileName)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DDDDDD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $exportMethod = 'exportar' . ucfirst($this->tipoReporte);
        if (method_exists($this, $exportMethod)) {
            $this->$exportMethod($sheet, $headerStyle);
        } else {
            throw new \Exception("Tipo de reporte no soportado");
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');
        $writer->save('php://output');
    }

    private function exportarBasico($sheet, $headerStyle)
    {
        $headers = ['ID', 'Usuario', 'Título', 'Estado', 'Fecha de creación'];
        $this->setHeaders($sheet, $headers, $headerStyle);

        $row = 2;
        foreach ($this->tickets as $ticket) {
            $sheet->fromArray([
                $ticket->id,
                $ticket->user->name,
                $ticket->titulo,
                $ticket->estado->nombre,
                $ticket->created_at->format('Y-m-d H:i:s')
            ], NULL, 'A' . $row);
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'E');
    }

    private function exportarDetallado($sheet, $headerStyle)
    {
        $headers = ['ID', 'Usuario', 'Título', 'Descripción', 'Departamento', 'Estado', 'Fecha de creación', 'Última actualización'];
        $this->setHeaders($sheet, $headers, $headerStyle);

        $row = 2;
        foreach ($this->tickets as $ticket) {
            $sheet->fromArray([
                $ticket->id,
                $ticket->user->name,
                $ticket->titulo,
                $ticket->descripcion,
                $ticket->departamento->nombre,
                $ticket->estado->nombre,
                $ticket->created_at->format('Y-m-d H:i:s'),
                $ticket->updated_at->format('Y-m-d H:i:s')
            ], NULL, 'A' . $row);
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'H');
    }

    private function exportarEstadistico($sheet, $headerStyle)
    {
        $headers = ['Estado', 'Cantidad', 'Porcentaje'];
        $this->setHeaders($sheet, $headers, $headerStyle);

        $ticketsPorEstado = $this->tickets->groupBy('estado.nombre');
        $totalTickets = $this->tickets->count();
        $row = 2;

        foreach ($ticketsPorEstado as $estado => $tickets) {
            $cantidad = $tickets->count();
            $porcentaje = ($cantidad / $totalTickets) * 100;
            $sheet->fromArray([
                $estado,
                $cantidad,
                number_format($porcentaje, 2) . '%'
            ], NULL, 'A' . $row);
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'C');
    }

    private function setHeaders($sheet, $headers, $headerStyle)
    {
        $sheet->fromArray([$headers], NULL, 'A1');
        $sheet->getStyle('A1:' . $this->getColumnLetter(count($headers)) . '1')->applyFromArray($headerStyle);
    }

    private function autoSizeColumns($sheet, $startColumn, $endColumn)
    {
        foreach(range($startColumn, $endColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function getColumnLetter($columnNumber)
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnNumber);
    }
}
