@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 flex items-center">
        <svg class="h-8 w-8 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Crear Nuevo Rol
    </h1>

    <form action="{{ route('roles.store') }}" method="POST" class="bg-white shadow-md rounded-lg px-8 pt-6 pb-8 mb-4">
        @csrf
        <div class="mb-4">
            <label for="roleName" class="block text-gray-700 text-sm font-bold mb-2">Nombre del Rol</label>
            <input type="text" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="roleName" name="name" required>
        </div>
        <div class="mb-4">
            <label for="permissions" class="block text-gray-700 text-sm font-bold mb-2">Permisos</label>
            <div class="grid grid-cols-2 gap-4">
                @foreach($permissions as $permission)
                    <div class="flex items-center">
                        <input class="form-checkbox h-5 w-5 text-blue-600 rounded-lg" type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="permission_{{ $permission->id }}">
                        <label class="ml-2 text-gray-700" for="permission_{{ $permission->id }}">
                            {{ $permission->name }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
            Guardar Rol
        </button>
    </form>
    <!-- Botón para regresar a la página anterior -->
    <button onclick="window.history.back()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg mb-4 inline-flex items-center">
        <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Volver
    </button>
</div>
@endsection
