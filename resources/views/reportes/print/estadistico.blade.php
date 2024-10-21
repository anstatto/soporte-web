<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Estadístico de Soportes</title>
    <style>
        /* Estilos básicos en línea */
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { font-size: 24px; margin-bottom: 20px; }
        h2 { font-size: 20px; margin-top: 30px; margin-bottom: 15px; }
        h3 { font-size: 18px; margin-top: 25px; margin-bottom: 10px; }
        ul { padding-left: 20px; }
        .grid { display: flex; flex-wrap: wrap; }
        .grid > div { flex: 1; min-width: 300px; margin: 10px; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Reporte Estadístico de Soportes</h1>
        <p>Período: {{ $fechaInicio ? $fechaInicio->format('d/m/Y H:i') : 'N/A' }} - {{ $fechaFin ? $fechaFin->format('d/m/Y H:i') : 'N/A' }}</p>
        @php
            $totalTickets = $ticketsPorUsuario->flatten()->count();
            $ticketsPorEstado = $ticketsPorUsuario->flatten()->groupBy('estado.nombre');
        @endphp
        <h2>Resumen General</h2>
        <p>Total de tickets: {{ $totalTickets }}</p>
        <h3>Tickets por Estado</h3>
        <div class="grid">
            <div>
                <ul>
                    @foreach($ticketsPorEstado as $estado => $tickets)
                        <li>{{ $estado }}: {{ $tickets->count() }} ({{ number_format($tickets->count() / $totalTickets * 100, 2) }}%)</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <canvas id="estadisticasChart"></canvas>
            </div>
        </div>
        <h2>Desglose por Usuario</h2>
        @foreach($ticketsPorUsuario as $userId => $tickets)
            <div>
                <h3>{{ $tickets->first()->user->name }}</h3>
                <p>Total de tickets: {{ $tickets->count() }}</p>
                @php
                    $ticketsUsuarioPorEstado = $tickets->groupBy('estado.nombre');
                @endphp
                <ul>
                    @foreach($ticketsUsuarioPorEstado as $estado => $ticketsEstado)
                        <li>{{ $estado }}: {{ $ticketsEstado->count() }} ({{ number_format($ticketsEstado->count() / $tickets->count() * 100, 2) }}%)</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>
