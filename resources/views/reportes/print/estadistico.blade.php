@extends('reportes.print.layout')

@section('title', 'Reporte estadístico de soportes')

@section('content')
    @php
        $all = $ticketsPorUsuario->flatten(1);
        $total = max($all->count(), 1);
        $porEstado = $all->groupBy(fn ($t) => $t->estado?->nombre ?? 'Sin estado');
        $porPrioridad = $all->groupBy(fn ($t) => ucfirst($t->prioridad ?? 'media'));
        $porDepto = $all->groupBy(fn ($t) => $t->departamento?->nombre ?? 'Sin departamento');
        $colors = ['#2F6FAD', '#1E4E79', '#3D7A5F', '#B7791F', '#C4554D', '#5B6B7C', '#6B5B7A', '#3D6B8A'];
    @endphp

    <div class="kpi-row">
        <div class="kpi">
            <div class="label">Total</div>
            <div class="value">{{ $all->count() }}</div>
        </div>
        <div class="kpi">
            <div class="label">Estados</div>
            <div class="value">{{ $porEstado->count() }}</div>
        </div>
        <div class="kpi">
            <div class="label">Departamentos</div>
            <div class="value">{{ $porDepto->count() }}</div>
        </div>
        <div class="kpi">
            <div class="label">Usuarios</div>
            <div class="value">{{ $ticketsPorUsuario->count() }}</div>
        </div>
    </div>

    <h2>Distribución por estado</h2>
    @foreach($porEstado as $nombre => $group)
        @php $pct = ($group->count() / $total) * 100; @endphp
        <div class="bar-wrap">
            <div class="bar-label">{{ $nombre }} — {{ $group->count() }} ({{ number_format($pct, 1) }}%)</div>
            <div class="bar-track">
                <div class="bar-fill" style="width: {{ min(100, $pct) }}%; background: {{ $colors[$loop->index % count($colors)] }};"></div>
            </div>
        </div>
    @endforeach

    <h2>Distribución por prioridad</h2>
    @foreach($porPrioridad as $nombre => $group)
        @php $pct = ($group->count() / $total) * 100; @endphp
        <div class="bar-wrap">
            <div class="bar-label">{{ $nombre }} — {{ $group->count() }} ({{ number_format($pct, 1) }}%)</div>
            <div class="bar-track">
                <div class="bar-fill" style="width: {{ min(100, $pct) }}%; background: {{ $colors[($loop->index + 2) % count($colors)] }};"></div>
            </div>
        </div>
    @endforeach

    <h2>Por departamento</h2>
    <table>
        <thead>
            <tr>
                <th>Departamento</th>
                <th style="width:20%">Cantidad</th>
                <th style="width:20%">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($porDepto as $nombre => $group)
                <tr>
                    <td>{{ $nombre }}</td>
                    <td>{{ $group->count() }}</td>
                    <td>{{ number_format(($group->count() / $total) * 100, 1) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Desglose por usuario</h2>
    @forelse($ticketsPorUsuario as $userId => $tickets)
        <div class="section">
            <h3>{{ $tickets->first()->user?->name ?? 'Sin usuario' }} — {{ $tickets->count() }} ticket(s)</h3>
            <ul>
                @foreach($tickets->groupBy(fn ($t) => $t->estado?->nombre ?? 'Sin estado') as $estado => $group)
                    <li>{{ $estado }}: {{ $group->count() }} ({{ number_format(($group->count() / max($tickets->count(), 1)) * 100, 1) }}%)</li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="muted">No hay tickets en el período seleccionado.</p>
    @endforelse
@endsection
