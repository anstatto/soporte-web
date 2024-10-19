<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Rendimiento de Soportes</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="container mx-auto p-4">
        <h1 class="text-3xl font-bold mb-4">Reporte de Rendimiento de Soportes</h1>
        @php
            $totalTickets = $ticketsPorUsuario->flatten()->count();
            $tiempoPromedioResolucion = $ticketsPorUsuario->flatten()->avg('tiempo_resolucion');
            $ticketsPorPrioridad = $ticketsPorUsuario->flatten()->groupBy('prioridad');
        @endphp
        <h2 class="text-2xl font-semibold mb-2">Resumen General</h2>
        <p class="mb-2">Total de tickets: {{ $totalTickets }}</p>
        <p class="mb-4">Tiempo promedio de resolución: {{ number_format($tiempoPromedioResolucion, 2) }} horas</p>

        <h3 class="text-xl font-semibold mb-2">Tickets por Prioridad</h3>
        <ul class="list-disc list-inside mb-4">
            @foreach($ticketsPorPrioridad as $prioridad => $tickets)
                <li>{{ $prioridad }}: {{ $tickets->count() }} ({{ number_format($tickets->count() / $totalTickets * 100, 2) }}%)</li>
            @endforeach
        </ul>

        <h2 class="text-2xl font-semibold mb-2">Rendimiento por Usuario</h2>
        @foreach($ticketsPorUsuario as $userId => $tickets)
            <div class="mb-4">
                <h3 class="text-xl font-semibold">{{ $tickets->first()->user->name }}</h3>
                <p>Total de tickets: {{ $tickets->count() }}</p>
                <p>Tiempo promedio de resolución: {{ number_format($tickets->avg('tiempo_resolucion'), 2) }} horas</p>
            </div>
        @endforeach
    </div>
</body>
</html>
