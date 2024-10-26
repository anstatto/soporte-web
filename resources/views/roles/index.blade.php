@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 flex items-center">
            <svg class="h-8 w-8 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m2 0a2 2 0 100-4H7a2 2 0 100 4h10z" />
            </svg>
            Roles Disponibles
        </h1>

        <div class="mb-4">
            <input type="text" id="search" placeholder="Buscar rol..."
                class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline transition duration-300 ease-in-out transform hover:scale-105">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-md">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 border-b text-left text-gray-600 font-semibold">Nombre del Rol</th>
                        <th class="py-3 px-4 border-b text-left text-gray-600 font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody id="rolesTable">
                    @foreach ($roles as $role)
                        <tr class="hover:bg-gray-50 transition duration-300 ease-in-out">
                            <td class="py-3 px-4 border-b">{{ $role->name }}</td>
                            <td class="py-3 px-4 border-b">
                                <a href="{{ route('roles.edit', $role->id) }}"
                                    class="text-blue-500 hover:text-blue-700 inline-flex items-center transition duration-300 ease-in-out transform hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <!-- Botón para regresar a la página anterior -->
        <button onclick="window.history.back()"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg mb-4 inline-flex items-center transition duration-300 ease-in-out transform hover:scale-105 mt-3">
            <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver
        </button>
    </div>

    <script>
        document.getElementById('search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#rolesTable tr');

            rows.forEach(row => {
                const roleName = row.cells[0].textContent.toLowerCase();
                row.style.display = roleName.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
@endsection
