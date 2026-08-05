@extends('reportes.print.layout')

@section('title', 'Reporte básico de soportes')

@section('content')
    @php
        $all = $ticketsPorUsuario->flatten(1);
        $total = $all->count();
    @endphp

    <div class="kpi-row">
        <div class="kpi">
            <div class="label">Total tickets</div>
            <div class="value">{{ $total }}</div>
        </div>
        <div class="kpi">
            <div class="label">Usuarios</div>
            <div class="value">{{ $ticketsPorUsuario->count() }}</div>
        </div>
    </div>

    @forelse($ticketsPorUsuario as $userId => $tickets)
        <div class="section">
            <h2>{{ $tickets->first()->user?->name ?? 'Sin usuario' }} ({{ $tickets->count() }})</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width:8%">ID</th>
                        <th>Título</th>
                        <th style="width:18%">Estado</th>
                        <th style="width:14%">Prioridad</th>
                        <th style="width:16%">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tickets as $ticket)
                        <tr>
                            <td>#{{ $ticket->id }}</td>
                            <td>{{ $ticket->titulo }}</td>
                            <td>
                                @if($ticket->estado)
                                    <span class="badge" style="background-color: {{ $ticket->estado->color ?? '#5B6B7C' }};">
                                        {{ $ticket->estado->nombre }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ ucfirst($ticket->prioridad ?? 'media') }}</td>
                            <td>{{ optional($ticket->created_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="muted">No hay tickets en el período seleccionado.</p>
    @endforelse
@endsection
