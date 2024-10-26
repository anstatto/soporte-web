@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6">Editar Ticket</h1>

        <form action="{{ route('tickets.update', $ticket) }}" method="POST" class="bg-white shadow-md rounded-lg px-8 pt-6 pb-8 mb-4">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="titulo">
                    Título
                </label>
                <input
                    class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="titulo" type="text" name="titulo" value="{{ $ticket->titulo }}" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="descripcion">
                    Descripción
                </label>
                <textarea
                    class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="descripcion" name="descripcion" rows="4" required>{{ $ticket->descripcion }}</textarea>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="departamento_id">
                    Departamento
                </label>
                <select
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="departamento_id" name="departamento_id" required>
                    @foreach ($departamentos as $departamento)
                        <option value="{{ $departamento->id }}"
                            {{ $ticket->departamento_id == $departamento->id ? 'selected' : '' }}>
                            {{ $departamento->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="estado_id">
                    Estado
                </label>
                <select
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="estado_id" name="estado_id" required>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado->id }}" {{ $ticket->estado_id == $estado->id ? 'selected' : '' }}>
                            {{ $estado->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="estado">
                    Estado Actual
                </label>
                <p
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    @if ($ticket->estado_id == 1)
                        En Proceso
                    @elseif($ticket->estado_id == 2)
                        Pendiente
                    @elseif($ticket->estado_id == 3)
                        Cancelada
                    @elseif($ticket->estado_id == 4)
                        Completa
                    @else
                        Desconocido
                    @endif
                </p>
            </div>
            @can('assign tickets')
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="user_ids">
                        Asignar Usuarios
                    </label>
                    <user-select
                        :users="{{ json_encode($usuarios) }}"
                        :initial-selected="{{ json_encode($ticket->users->pluck('id')) }}"
                        @update:selected="updateSelectedUsers">
                    </user-select>
                </div>
            @endcan
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="fecha_entrega">
                    Fecha de Entrega
                </label>
                <input type="datetime-local"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="fecha_entrega" name="fecha_entrega" value="{{ $ticket->fecha_entrega ? \Carbon\Carbon::parse($ticket->fecha_entrega)->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i') }}" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="recordatorio">
                    Recordatorio
                </label>
                <input type="datetime-local"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    id="recordatorio" name="recordatorio" value="{{ $ticket->recordatorio ? \Carbon\Carbon::parse($ticket->recordatorio)->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i') }}" required>
            </div>
            <div class="flex items-center justify-between">
                <button
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline"
                    type="submit">
                    Actualizar Ticket
                </button>
            </div>
        </form>
    </div>
@endsection
