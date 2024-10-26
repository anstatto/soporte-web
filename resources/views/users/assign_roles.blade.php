@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 flex items-center">
        <svg class="h-8 w-8 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 0a2 2 0 100-4H7a2 2 0 100 4h10z" />
        </svg>
        Asignar Roles a Usuarios
    </h1>

    <!-- Botón para abrir la vista de creación de roles -->
    <a href="{{ route('roles.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mb-4 inline-flex items-center transition duration-300 ease-in-out transform hover:scale-105">
        <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Crear Nuevo Rol
    </a>

    <!-- Botón para consultar roles -->
    <a href="{{ route('roles.index') }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mb-4 inline-flex items-center transition duration-300 ease-in-out transform hover:scale-105 ml-3">
        <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.5 10.5a5 5 0 11-1.414-1.414l4.95 4.95-1.414 1.414-4.95-4.95A5 5 0 0115.5 10.5z" />
        </svg>
        Consultar Roles
    </a>

    <!-- Campo de búsqueda para usuarios -->
    <div class="mb-4">
        <input type="text" id="searchUser" placeholder="Buscar usuario..." class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
    </div>

    <!-- Formulario para asignar roles a usuarios -->
    <form action="{{ route('users.assignRoles') }}" method="POST" class="bg-white shadow-md rounded-lg px-8 pt-6 pb-8 mb-4 transition duration-300 ease-in-out transform hover:shadow-lg">
        @csrf
        <div class="mb-4">
            <label for="user" class="block text-gray-700 text-sm font-bold mb-2">Usuario</label>
            <select name="user_id" id="user" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="updateRoles()">
                @foreach($users as $user)
                    <option value="{{ $user->id }}" data-roles="{{ $user->roles->pluck('name') }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-4">
            <label for="roles" class="block text-gray-700 text-sm font-bold mb-2">Roles</label>
            <div class="grid grid-cols-2 gap-4">
                @foreach($roles as $role)
                    <div class="flex items-center">
                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" id="role_{{ $role->id }}" class="form-checkbox h-5 w-5 text-blue-600 rounded-lg">
                        <label for="role_{{ $role->id }}" class="ml-2 text-gray-700">{{ $role->name }}</label>
                    </div>
                @endforeach
            </div>
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out transform hover:scale-105">
            Asignar Roles
        </button>
    </form>

    <!-- Botón para regresar a la página anterior -->
    <button onclick="window.history.back()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mb-4 inline-flex items-center transition duration-300 ease-in-out transform hover:scale-105">
        <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Volver
    </button>
</div>
@endsection

@push('scripts')
<script>
    function updateRoles() {
        const userSelect = document.getElementById('user');
        const selectedOption = userSelect.options[userSelect.selectedIndex];
        const userRoles = JSON.parse(selectedOption.getAttribute('data-roles'));

        document.querySelectorAll('input[type="checkbox"][name="roles[]"]').forEach(checkbox => {
            checkbox.checked = false;
        });

        userRoles.forEach(role => {
            const checkbox = document.querySelector(`input[type="checkbox"][value="${role}"]`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }

    // Inicializar roles para el primer usuario seleccionado
    document.addEventListener('DOMContentLoaded', updateRoles);

    // Filtrar usuarios
    document.getElementById('searchUser').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const userOptions = document.querySelectorAll('#user option');

        userOptions.forEach(option => {
            const userName = option.textContent.toLowerCase();
            option.style.display = userName.includes(searchTerm) ? '' : 'none';
        });

        // Resetear la selección si el usuario no está visible
        if (!Array.from(userOptions).some(option => option.style.display === '' && option.selected)) {
            userOptions[0].selected = true; // Seleccionar el primer usuario visible
            updateRoles(); // Actualizar roles
        }
    });
</script>
@endpush
