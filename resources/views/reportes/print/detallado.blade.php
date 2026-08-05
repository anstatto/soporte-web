@extends('reportes.print.layout')

@section('title', 'Reporte detallado de soportes')

@section('content')
    @forelse($ticketsPorUsuario as $userId => $tickets)
        <div class="section">
            <h2>{{ $tickets->first()->user?->name ?? 'Sin usuario' }} ({{ $tickets->count() }})</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width:6%">ID</th>
                        <th style="width:18%">Título</th>
                        <th>Descripción</th>
                        <th style="width:12%">Depto.</th>
                        <th style="width:12%">Estado</th>
                        <th style="width:10%">Prioridad</th>
                        <th style="width:12%">Creado</th>
                        <th style="width:12%">Actualizado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tickets as $ticket)
                        <tr>
                            <td>#{{ $ticket->id }}</td>
                            <td>{{ $ticket->titulo }}</td>
                            <td>{{ \Illuminate\Support\Str::limit(strip_tags($ticket->descripcion ?? ''), 120) }}</td>
                            <td>{{ $ticket->departamento?->nombre ?? '—' }}</td>
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
                            <td>{{ optional($ticket->updated_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="muted">No hay tickets en el período seleccionado.</p>
    @endforelse
@endsection
