<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Soportes</title>
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            /* Aquí puedes agregar más estilos específicos para impresión */
        }
        /* Tus estilos normales aquí */
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-4">
        <h1 class="text-3xl font-bold text-center mb-6">Reporte Básico de Soportes</h1>
        @foreach($ticketsPorUsuario as $userId => $tickets)
            <h2 class="text-2xl font-semibold mb-4">Usuario: {{ $tickets->first()->user->name }}</h2>
            <div class="overflow-x-auto shadow-md rounded-lg">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-sm">
                        <tr>
                            <th class="py-3 px-6 text-left">Título</th>
                            <th class="py-3 px-6 text-left">Estado</th>
                            <th class="py-3 px-6 text-left">Fecha de Creación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($tickets as $ticket)
                            <tr class="hover:bg-gray-50">
                                <td class="py-4 px-6">{{ $ticket->titulo }}</td>
                                <td class="py-4 px-6">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full" style="background-color: {{ $ticket->estado->color }}; color: {{ $ticket->estado->color == '#FFFFFF' ? '#000000' : '#FFFFFF' }};">
                                        {{ $ticket->estado->nombre }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">{{ $ticket->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</body>
</html>
