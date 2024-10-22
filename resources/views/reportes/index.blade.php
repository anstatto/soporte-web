@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-2 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
        </svg>
        Reportes de Soportes
    </h1>

    <form action="{{ route('reportes.index') }}" method="GET" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <div class="flex flex-wrap -mx-3 mb-4">
            <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="fecha_inicio">
                    Fecha Inicio
                </label>
                <input type="datetime-local" id="fecha_inicio" name="fecha_inicio"
                       value="{{ $fechaInicio->format('Y-m-d\TH:i') }}" class="appearance-none block w-full bg-gray-200 text-gray-700 border rounded py-3 px-4 mb-3 leading-tight focus:outline-none focus:bg-white">
            </div>
            <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="fecha_fin">
                    Fecha Fin
                </label>
                <input type="datetime-local" id="fecha_fin" name="fecha_fin"
                       value="{{ $fechaFin->format('Y-m-d\TH:i') }}" class="appearance-none block w-full bg-gray-200 text-gray-700 border rounded py-3 px-4 mb-3 leading-tight focus:outline-none focus:bg-white">
            </div>
            <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="user_id">
                    Usuario
                </label>
                <select id="user_id" name="user_id" class="block appearance-none w-full bg-gray-200 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                    <option value="">Todos los usuarios</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="estado_id">
                    Estado
                </label>
                <select id="estado_id" name="estado_id" class="block appearance-none w-full bg-gray-200 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                    <option value="">Todos los estados</option>
                    @foreach($estados as $estado)
                        <option value="{{ $estado->id }}" {{ request('estado_id') == $estado->id ? 'selected' : '' }}>{{ $estado->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0 flex items-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    Filtrar
                </button>
            </div>
        </div>
    </form>

    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-2xl font-bold mb-4">Exportar o Imprimir Reporte</h2>
        <form action="{{ route('reportes.exportar') }}" method="POST" class="flex items-center flex-wrap">
            @csrf
            <input type="hidden" name="fecha_inicio" value="{{ request('fecha_inicio') }}">
            <input type="hidden" name="fecha_fin" value="{{ request('fecha_fin') }}">
            <input type="hidden" name="user_id" value="{{ request('user_id') }}">
            <div class="mr-4 mb-4">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="tipo_reporte">
                    Tipo de Reporte
                </label>
                <select name="tipo_reporte" id="tipo_reporte" class="block appearance-none w-full bg-gray-200 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                    @foreach($tiposReporte as $valor => $nombre)
                        <option value="{{ $valor }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mr-4 mb-4">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="format">
                    Formato
                </label>
                <select name="format" id="format" class="block appearance-none w-full bg-gray-200 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    Exportar
                </button>
                <button type="button" onclick="imprimirReporte()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                    </svg>
                    Imprimir
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-2xl font-bold mb-4">Resultados</h2>
        @if($tickets->isEmpty())
            <p class="text-gray-600">No se encontraron tickets que coincidan con los criterios de búsqueda.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">Título</th>
                            <th class="py-3 px-6 text-left">Usuario</th>
                            <th class="py-3 px-6 text-left">Departamento</th>
                            <th class="py-3 px-6 text-left">Estado</th>
                            <th class="py-3 px-6 text-left">Fecha de Creación</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        @foreach($tickets as $ticket)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap">{{ $ticket->titulo }}</td>
                                <td class="py-3 px-6 text-left">{{ $ticket->user->name }}</td>
                                <td class="py-3 px-6 text-left">{{ $ticket->departamento->nombre }}</td>
                                <td class="py-3 px-6 text-left">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" style="background-color: {{ $ticket->estado->color }}; color: {{ $ticket->estado->color == '#FFFFFF' ? '#000000' : '#FFFFFF' }};">
                                        {{ $ticket->estado->nombre }}
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-left">{{ $ticket->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $tickets->appends(request()->except('page'))->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
window.imprimirReporte = function() {
    const tipoReporte = document.getElementById('tipo_reporte').value;
    const fechaInicio = document.querySelector('input[name="fecha_inicio"]').value;
    const fechaFin = document.querySelector('input[name="fecha_fin"]').value;
    const userId = document.querySelector('select[name="user_id"]').value;
    const estadoId = document.querySelector('select[name="estado_id"]').value;

    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('tipo_reporte', tipoReporte);
    formData.append('fecha_inicio', fechaInicio);
    formData.append('fecha_fin', fechaFin);
    formData.append('user_id', userId);
    formData.append('estado_id', estadoId);

    fetch('{{ route('reportes.imprimir') }}', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(data.html);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            const style = printWindow.document.createElement('style');
            style.textContent = `
                @media print {
                    body {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                }
            `;
            printWindow.document.head.appendChild(style);
            printWindow.print();
            printWindow.close();
        }, 250);
    })
    .catch(error => console.error('Error:', error));
};

document.addEventListener('DOMContentLoaded', function() {
    // Si prefieres usar un event listener en lugar de onclick, puedes descomentar la siguiente línea
    // document.getElementById('btnImprimir').addEventListener('click', imprimirReporte);
});
</script>
@endpush
