@extends('reportes.print.layout')

@section('title', 'Reporte de rendimiento')

@section('content')
    @php
        $all = $ticketsPorUsuario->flatten(1);
        $total = $all->count();
        $cerrados = $all->filter(fn ($t) => $t->tiempo_resolucion_horas !== null);
        $promedioGlobal = $cerrados->avg('tiempo_resolucion_horas');
        $porPrioridad = $all->groupBy(fn ($t) => ucfirst($t->prioridad ?? 'media'));
        $abiertos = $total - $cerrados->count();
    @endphp

    <div class="kpi-row">
        <div class="kpi">
            <div class="label">Total</div>
            <div class="value">{{ $total }}</div>
        </div>
        <div class="kpi">
            <div class="label">Cerrados</div>
            <div class="value">{{ $cerrados->count() }}</div>
        </div>
        <div class="kpi">
            <div class="label">Abiertos</div>
            <div class="value">{{ $abiertos }}</div>
        </div>
        <div class="kpi">
            <div class="label">Prom. resolución</div>
            <div class="value">
                @if($promedioGlobal !== null)
                    {{ number_format($promedioGlobal, 1) }}h
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    <h2>Tickets por prioridad</h2>
    @if($total === 0)
        <p class="muted">No hay tickets en el período seleccionado.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Prioridad</th>
                    <th style="width:20%">Cantidad</th>
                    <th style="width:20%">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porPrioridad as $prioridad => $group)
                    <tr>
                        <td>{{ $prioridad }}</td>
                        <td>{{ $group->count() }}</td>
                        <td>{{ number_format(($group->count() / max($total, 1)) * 100, 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Rendimiento por usuario</h2>
    <table>
        <thead>
            <tr>
                <th>Usuario</th>
                <th style="width:14%">Total</th>
                <th style="width:14%">Cerrados</th>
                <th style="width:18%">Prom. horas</th>
                <th style="width:18%">Tasa cierre</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ticketsPorUsuario as $userId => $tickets)
                @php
                    $uCerrados = $tickets->filter(fn ($t) => $t->tiempo_resolucion_horas !== null);
                    $prom = $uCerrados->avg('tiempo_resolucion_horas');
                    $tasa = $tickets->count() > 0 ? ($uCerrados->count() / $tickets->count()) * 100 : 0;
                @endphp
                <tr>
                    <td>{{ $tickets->first()->user?->name ?? 'Sin usuario' }}</td>
                    <td>{{ $tickets->count() }}</td>
                    <td>{{ $uCerrados->count() }}</td>
                    <td>{{ $prom !== null ? number_format($prom, 1) . ' h' : '—' }}</td>
                    <td>{{ number_format($tasa, 1) }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Sin datos</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle de tickets cerrados</h2>
    <table>
        <thead>
            <tr>
                <th style="width:8%">ID</th>
                <th>Título</th>
                <th style="width:18%">Usuario</th>
                <th style="width:12%">Prioridad</th>
                <th style="width:14%">Horas</th>
                <th style="width:16%">Cerrado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cerrados->sortByDesc('tiempo_resolucion_horas') as $ticket)
                <tr>
                    <td>#{{ $ticket->id }}</td>
                    <td>{{ $ticket->titulo }}</td>
                    <td>{{ $ticket->user?->name ?? '—' }}</td>
                    <td>{{ ucfirst($ticket->prioridad ?? 'media') }}</td>
                    <td>{{ number_format($ticket->tiempo_resolucion_horas, 1) }} h</td>
                    <td>{{ optional($ticket->updated_at)->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No hay tickets cerrados en el período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
