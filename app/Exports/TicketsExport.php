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

        switch ($this->tipoReporte) {
            case 'basico':
                $this->exportarBasico($sheet, $headerStyle);
                break;
            case 'detallado':
                $this->exportarDetallado($sheet, $headerStyle);
                break;
            case 'estadistico':
                $this->exportarEstadistico($sheet, $headerStyle);
                break;
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');
        $writer->save('php://output');
    }

    private function exportarBasico($sheet, $headerStyle)
    {
        $headers = ['ID', 'Usuario', 'Título', 'Estado', 'Fecha de creación'];
        $sheet->fromArray([$headers], NULL, 'A1');
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

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

        foreach(range('A','E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function exportarDetallado($sheet, $headerStyle)
    {
        $headers = ['ID', 'Usuario', 'Título', 'Descripción', 'Departamento', 'Estado', 'Fecha de creación', 'Última actualización'];
        $sheet->fromArray([$headers], NULL, 'A1');
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

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

        foreach(range('A','H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function exportarEstadistico($sheet, $headerStyle)
    {
        $headers = ['Estado', 'Cantidad', 'Porcentaje'];
        $sheet->fromArray([$headers], NULL, 'A1');
        $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

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

        foreach(range('A','C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
