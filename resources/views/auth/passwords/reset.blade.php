@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex justify-center items-center min-h-screen bg-gray-100">
        <div class="w-full max-w-md">
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-400 to-blue-500 px-6 py-4">
                    <h2 class="text-white text-2xl font-bold">{{ __('Restablecer Contraseña') }}</h2>
                </div>

                <div class="px-6 py-8">
                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf

                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="mb-6">
                            <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">{{ __('Dirección de Correo Electrónico') }}</label>
                            <input id="email" type="email" class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('email') border-red-500 @enderror" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus>
                            @error('email')
                                <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">{{ __('Contraseña') }}</label>
                            <input id="password" type="password" class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('password') border-red-500 @enderror" name="password" required autocomplete="new-password">
                            @error('password')
                                <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="password-confirm" class="block text-gray-700 text-sm font-semibold mb-2">{{ __('Confirmar Contraseña') }}</label>
                            <input id="password-confirm" type="password" class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" name="password_confirmation" required autocomplete="new-password">
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-gradient-to-r from-green-400 to-blue-500 hover:from-green-600 hover:to-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                {{ __('Restablecer Contraseña') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
