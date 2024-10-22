@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold mb-4 text-center">Mi Perfil</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('perfil.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="flex flex-col">
            <label for="name" class="mb-1 font-semibold flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4.992 4.992 0 0112 15c1.657 0 3.156.672 4.121 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zM12 14v7m0 0H9m3 0h3"></path>
                </svg>
                Nombre
            </label>
            <input type="text" name="name" id="name" class="border border-gray-300 p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ old('name', $user->name) }}" required>
        </div>

        <div class="flex flex-col">
            <label for="email" class="mb-1 font-semibold flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12H8m0 0l4-4m-4 4l4 4m8-4a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Correo Electrónico
            </label>
            <input type="email" name="email" id="email" class="border border-gray-300 p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ old('email', $user->email) }}" required>
        </div>

        <div class="flex flex-col">
            <label for="username" class="mb-1 font-semibold flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 1.657-1.343 3-3 3S6 12.657 6 11s1.343-3 3-3 3 1.343 3 3zM12 11c0 1.657 1.343 3 3 3s3-1.343 3-3-1.343-3-3-3-3 1.343-3 3zM12 11v10m0 0H9m3 0h3"></path>
                </svg>
                Nombre de Usuario
            </label>
            <input type="text" name="username" id="username" class="border border-gray-300 p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ old('username', $user->username) }}" required>
        </div>

        <div class="flex flex-col">
            <label for="departamento_id" class="mb-1 font-semibold flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"></path>
                </svg>
                Departamento
            </label>
            <select name="departamento_id" id="departamento_id" class="border border-gray-300 p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                @foreach($departamentos as $departamento)
                    <option value="{{ $departamento->id }}" {{ $user->departamento_id == $departamento->id ? 'selected' : '' }}>
                        {{ $departamento->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Agrega otros campos que desees permitir que el usuario edite -->

        <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-md hover:bg-blue-600 transition duration-300 flex items-center justify-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Actualizar Perfil
        </button>
    </form>
</div>
@endsection
