@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="bg-red-500 text-white px-6 py-4">
                <h1 class="text-2xl font-bold">403 - Acceso denegado</h1>
            </div>
            <div class="px-6 py-4">
                <p class="text-gray-700">No tienes permiso para acceder a esta página.</p>
                <div class="mt-6 text-center">
                    <a href="{{ route('home') }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out inline-flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                        Volver al inicio
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
