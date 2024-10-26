<!-- resources/js/components/UserSelect.vue -->
<template>
    <div>
        <label class="block text-gray-700 text-sm font-bold mb-2" for="user_ids">
            Asignar Usuarios
        </label>
        <select
            v-model="selectedUsers"
            multiple
            class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
        >
            <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.name }}
            </option>
        </select>
    </div>
</template>

<script>
export default {
    props: {
        users: {
            type: Array,
            required: true
        },
        initialSelected: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            selectedUsers: this.initialSelected
        };
    },
    watch: {
        selectedUsers(newVal) {
            this.$emit('update:selected', newVal);
        }
    }
};
</script>

<style scoped>
/* Puedes agregar estilos personalizados aquí */
@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-2 text-blue-500" viewBox="0 0 20 20"
                    fill="currentColor">
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                    <path fill-rule="evenodd"
                        d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                        clip-rule="evenodd" />
                </svg>
                Lista de Tickets
            </h1>
            @can('create tickets')
                <a href="{{ route('tickets.create') }}"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 ease-in-out flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                            clip-rule="evenodd" />
                    </svg>
                    Crear Nuevo Ticket
                </a>
            @endcan
        </div>
        <form action="{{ route('tickets.index') }}" method="GET"
            class="mb-8 bg-white shadow-lg rounded-lg px-8 pt-6 pb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="titulo">Título</label>
                    <input
                        class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="titulo" type="text" name="titulo" value="{{ request('titulo') }}">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="departamento_id">Departamento</label>
                    <select
                        class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="departamento_id" name="departamento_id">
                        <option value="">Todos los departamentos</option>
                        @foreach ($departamentos as $departamento)
                            <option value="{{ $departamento->id }}"
                                {{ request('departamento_id') == $departamento->id ? 'selected' : '' }}>
                                {{ $departamento->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="estado_id">Estado</label>
                    <select
                        class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="estado_id" name="estado_id">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $estado)
                            <option value="{{ $estado->id }}" {{ request('estado_id') == $estado->id ? 'selected' : '' }}>
                                {{ $estado->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="fecha_inicio">Fecha y Hora Inicio</label>
                    <input
                        class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="fecha_inicio" type="datetime-local" name="fecha_inicio"
                        value="{{ request('fecha_inicio', now()->format('Y-m-d') . 'T07:00') }}">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="fecha_fin">Fecha y Hora Fin</label>
                    <input
                        class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="fecha_fin" type="datetime-local" name="fecha_fin"
                        value="{{ request('fecha_fin', now()->format('Y-m-d') . 'T18:00') }}">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="order_by">Ordenar por</label>
                    <select
                        class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="order_by" name="order_by">
                        <option value="created_at" {{ request('order_by') == 'created_at' ? 'selected' : '' }}>Fecha de
                            creación</option>
                        <option value="titulo" {{ request('order_by') == 'titulo' ? 'selected' : '' }}>Título</option>
                        <option value="departamento_id" {{ request('order_by') == 'departamento_id' ? 'selected' : '' }}>
                            Departamento</option>
                        <option value="estado_id" {{ request('order_by') == 'estado_id' ? 'selected' : '' }}>Estado
                        </option>
                    </select>
                </div>
            </div>
            <div class="mt-6">
                <button type="submit"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 ease-in-out">
                    Filtrar
                </button>
            </div>
        </form>

        <!-- Tabla de tickets -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Título</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Departamento</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Estado</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Fecha de Creación</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Fecha de Entrega</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Creado por</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Usuarios Asignados</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap font-medium">{{ $ticket->titulo }}</p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap">{{ $ticket->departamento->nombre }}</p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    @php
                                        $hexColor = ltrim($ticket->estado->color, '#');
                                        $r = hexdec(substr($hexColor, 0, 2));
                                        $g = hexdec(substr($hexColor, 2, 2));
                                        $b = hexdec(substr($hexColor, 4, 2));
                                        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                                        $textColor = $luminance > 0.5 ? '#000000' : '#FFFFFF';
                                    @endphp
                                    <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        style="background-color: {{ $ticket->estado->color }}; color: {{ $textColor }};">
                                        {{ $ticket->estado->nombre }}
                                    </span>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap">
                                        {{ $ticket->created_at instanceof \Carbon\Carbon ? $ticket->created_at->format('d/m/Y H:i') : 'Fecha no válida' }}
                                    </p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap">
                                        {{ $ticket->fecha_entrega }} <!-- Esto mostrará el valor de fecha_entrega -->
                                    </p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap">
                                        {{ $ticket->user->name }} <!-- Mostrar el creador del ticket -->
                                    </p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap">
                                        @foreach ($ticket->users as $user)
                                            {{ $user->name }}@if (!$loop->last), @endif
                                        @endforeach
                                    </p>
                                </td>
                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                    <div class="flex items-center space-x-4">
                                        <a href="{{ route('tickets.show', $ticket) }}"
                                            class="text-blue-600 hover:text-blue-900 transition duration-300 ease-in-out">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd"
                                                    d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                        @can('edit tickets')
                                            <a href="{{ route('tickets.edit', $ticket) }}"
                                                class="text-yellow-600 hover:text-yellow-900 transition duration-300 ease-in-out">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                                    fill="currentColor">
                                                    <path
                                                        d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                </svg>
                                            </a>
                                        @endcan
                                        @can('delete tickets')
                                            <form action="{{ route('tickets.destroy', $ticket) }}" method="POST"
                                                class="inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-red-600 hover:text-red-900 transition duration-300 ease-in-out"
                                                    onclick="return confirm('¿Estás seguro de eliminar este Tickets?')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                        viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            {{ $tickets->links() }}
        </div>
    </div>
@endsection
