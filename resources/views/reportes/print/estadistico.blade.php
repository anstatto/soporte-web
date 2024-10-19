<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Estadístico de Soportes</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="container mx-auto p-4">
        <h1 class="text-3xl font-bold mb-4">Reporte Estadístico de Soportes</h1>
        <p class="mb-4">Período: {{ $fechaInicio ? $fechaInicio->format('d/m/Y H:i') : 'N/A' }} - {{ $fechaFin ? $fechaFin->format('d/m/Y H:i') : 'N/A' }}</p>
        @php
            $totalTickets = $ticketsPorUsuario->flatten()->count();
            $ticketsPorEstado = $ticketsPorUsuario->flatten()->groupBy('estado.nombre');
        @endphp
        <h2 class="text-2xl font-semibold mb-2">Resumen General</h2>
        <p class="mb-4">Total de tickets: {{ $totalTickets }}</p>
        <h3 class="text-xl font-semibold mb-2">Tickets por Estado</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <ul class="list-disc list-inside mb-4">
                    @foreach($ticketsPorEstado as $estado => $tickets)
                        <li>{{ $estado }}: {{ $tickets->count() }} ({{ number_format($tickets->count() / $totalTickets * 100, 2) }}%)</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <canvas id="estadisticasChart"></canvas>
            </div>
        </div>
        <h2 class="text-2xl font-semibold mb-2 mt-6">Desglose por Usuario</h2>
        @foreach($ticketsPorUsuario as $userId => $tickets)
            <div class="mb-4">
                <h3 class="text-xl font-semibold">{{ $tickets->first()->user->name }}</h3>
                <p>Total de tickets: {{ $tickets->count() }}</p>
                @php
                    $ticketsUsuarioPorEstado = $tickets->groupBy('estado.nombre');
                @endphp
                <ul class="list-disc list-inside">
                    @foreach($ticketsUsuarioPorEstado as $estado => $ticketsEstado)
                        <li>{{ $estado }}: {{ $ticketsEstado->count() }} ({{ number_format($ticketsEstado->count() / $tickets->count() * 100, 2) }}%)</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
    <script>
        const ctx = document.getElementById('estadisticasChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: {!! json_encode($ticketsPorEstado->keys()) !!},
                datasets: [{
                    data: {!! json_encode($ticketsPorEstado->map->count()->values()) !!},
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                    ],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Distribución de Tickets por Estado'
                    }
                }
            }
        });
    </script>
</body>
</html>
