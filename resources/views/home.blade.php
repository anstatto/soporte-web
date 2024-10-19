@extends('layouts.app')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-blue-600 mb-4">Dashboard</h2>
        <p class="text-gray-600">¡Bienvenido! Has iniciado sesión correctamente.</p>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-blue-600 mb-4">Resumen de Tickets</h2>
        <ul class="space-y-2">
            <li class="flex justify-between items-center">
                <span class="text-gray-600">Total de tickets:</span>
                <span class="font-semibold">{{ App\Models\Ticket::count() }}</span>
            </li>
            <li class="flex justify-between items-center">
                <span class="text-gray-600">Tickets abiertos:</span>
                <span class="font-semibold">{{ App\Models\Ticket::where('estado_id', 1)->count() }}</span>
            </li>
            <li class="flex justify-between items-center">
                <span class="text-gray-600">Tickets cerrados:</span>
                <span class="font-semibold">{{ App\Models\Ticket::where('estado_id', 3)->count() }}</span>
            </li>
        </ul>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-blue-600 mb-4">Tus Estadísticas</h2>
        <ul class="space-y-2">
            <li class="flex justify-between items-center">
                <span class="text-gray-600">Tickets creados:</span>
                <span class="font-semibold">{{ Auth::user()->tickets()->count() }}</span>
            </li>
            <li class="flex justify-between items-center">
                <span class="text-gray-600">Comentarios realizados:</span>
                <span class="font-semibold">{{ Auth::user()->comentarios()->count() }}</span>
            </li>
        </ul>
    </div>
</div>

<div class="mt-8 bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-blue-600 mb-4">Actividad Reciente</h2>
    <ul class="space-y-4">
        @foreach(App\Models\Ticket::latest()->take(5)->get() as $ticket)
            <li class="border-b pb-2">
                <span class="font-semibold">{{ $ticket->user->name }}</span> creó un nuevo ticket:
                <a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">{{ $ticket->titulo }}</a>
            </li>
        @endforeach
    </ul>
</div>
@endsection
