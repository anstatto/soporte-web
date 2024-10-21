@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex justify-center items-center min-h-screen bg-gray-100">
        <div class="w-full max-w-md">
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-purple-400 to-pink-500 px-6 py-4">
                    <h2 class="text-white text-2xl font-bold">{{ __('Verifica tu dirección de correo electrónico') }}</h2>
                </div>

                <div class="px-6 py-8">
                    @if (session('resent'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                            {{ __('Se ha enviado un nuevo enlace de verificación a tu dirección de correo electrónico.') }}
                        </div>
                    @endif

                    <p class="mb-4 text-gray-700">{{ __('Antes de continuar, por favor revisa tu correo electrónico para un enlace de verificación.') }}</p>
                    <p class="mb-4 text-gray-700">{{ __('Si no recibiste el correo electrónico') }},</p>
                    <form class="inline" method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button type="submit" class="bg-gradient-to-r from-purple-400 to-pink-500 hover:from-purple-600 hover:to-pink-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            {{ __('haz clic aquí para solicitar otro') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
