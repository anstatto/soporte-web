@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Detalles del Ticket</h1>

    <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-4">
            <h2 class="text-2xl font-semibold text-gray-800 mb-2">{{ $ticket->titulo }}</h2>
            <p class="text-gray-600 mb-4">{{ $ticket->descripcion }}</p>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <span class="text-sm font-semibold text-gray-600">Departamento:</span>
                    <span class="ml-2 text-sm text-gray-800">{{ $ticket->departamento->nombre }}</span>
                </div>
                @php
                    $hexColor = ltrim($ticket->estado->color, '#');
                    $r = hexdec(substr($hexColor, 0, 2));
                    $g = hexdec(substr($hexColor, 2, 2));
                    $b = hexdec(substr($hexColor, 4, 2));
                    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                @endphp
                <div>
                    <span class="text-sm font-semibold text-gray-600">Estado:</span>
                    <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                         {{ $luminance > 0.5 ? 'text-black' : 'text-white' }}"
                          style="background-color: {{ $ticket->estado->color }};">
                        {{ $ticket->estado->nombre }}
                    </span>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <a href="{{ route('tickets.edit', $ticket) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                    </svg>
                    Editar
                </a>
                <form action="{{ route('tickets.destroy', $ticket) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out flex items-center" onclick="return confirm('¿Estás seguro?')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Sección de comentarios -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-4">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Comentarios</h3>
            @foreach($ticket->comentarios as $comentario)
            <div class="mb-4 pb-4 border-b border-gray-200">
                <p class="text-gray-800">{{ $comentario->contenido }}</p>
                <p class="text-sm text-gray-600 mt-1">Por {{ $comentario->user->name }} - {{ $comentario->created_at->diffForHumans() }}</p>
            </div>
            @endforeach

            <!-- Formulario para agregar comentarios -->
            <form action="{{ route('comentarios.store', $ticket) }}" method="POST" class="mt-4">
                @csrf
                <div class="mb-4">
                    <label for="contenido" class="block text-gray-700 text-sm font-bold mb-2">Nuevo comentario:</label>
                    <textarea name="contenido" id="contenido" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required></textarea>
                </div>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out">
                    Agregar comentario
                </button>
            </form>
        </div>
    </div>

    <a href="{{ route('tickets.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out inline-flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
        </svg>
        Volver a la lista
    </a>
</div>
@endsection

