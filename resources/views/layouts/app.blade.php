<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RM Consuegra SRL Soporte</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="{{ asset('images/LogoMono.png') }}" type="image/x-icon">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100 min-h-screen font-sans antialiased">
    <div id="app" class="flex flex-col min-h-screen">
        <!-- Mensajes Flash -->
        @if (session('success'))
            <div v-if="show"
                class="fixed bottom-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg z-50 transition-transform transform ease-in-out duration-300">
                {{ session('success') }}
                <button @click="show = false" class="ml-2 text-white">&times;</button>
            </div>
        @endif

        @if (session('error'))
            <div v-if="show"
                class="fixed bottom-4 right-4 bg-red-500 text-white p-4 rounded-lg shadow-lg z-50 transition-transform transform ease-in-out duration-300">
                {{ session('error') }}
                <button @click="show = false" class="ml-2 text-white">&times;</button>
            </div>
        @endif

        <nav class="bg-white shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ url('/') }}" class="flex-shrink-0 flex items-center group">
                            <img src="{{ asset('images/LogoMono.png') }}" alt="Logo"
                                class="h-8 w-auto mr-2 transition-transform duration-1000 group-hover:rotate-360 transform hover:scale-125 hover:rotate-360">
                            <span
                                class="text-xl font-bold text-blue-600 transition-colors duration-1000 group-hover:text-purple-600 hidden sm:inline">RM
                                Consuegra SRL Soporte</span>
                        </a>
                    </div>
                    <div class="hidden sm:flex sm:items-center">
                        @guest
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}"
                                    class="text-gray-700 hover:bg-gray-100 px-3 py-2 rounded-md text-sm font-medium">{{ __('Login') }}</a>
                            @endif
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                    class="text-gray-700 hover:bg-gray-100 px-3 py-2 rounded-md text-sm font-medium">{{ __('Register') }}</a>
                            @endif
                        @else
                            <notifications :user-id="{{ Auth::user()->id }}"></notifications>
                            <user-menu :user-name="'{{ Auth::user()->name }}'" :logout-url="'{{ route('logout') }}'"
                                :csrf-token="'{{ csrf_token() }}'"
                                :profile-url="'{{ route('perfil.show') }}'"></user-menu>
                            @role('admin')
                                <a href="{{ route('users.showAssignRoles') }}"
                                    class="text-gray-700 hover:bg-gray-100 px-3 py-2 rounded-md text-sm font-medium">Asignar
                                    Roles</a>
                            @endrole
                        @endguest
                    </div>
                    <div class="-mr-2 flex items-center sm:hidden">
                        <button @click="open = !open"
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                            <span class="sr-only">Abrir menú principal</span>
                            <svg class="h-6 w-6" v-if="!open" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg class="h-6 w-6" v-if="open" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div v-show="open" class="sm:hidden">
                <div class="pt-2 pb-3 space-y-1">
                    @guest
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}"
                                class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">{{ __('Login') }}</a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                                class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">{{ __('Register') }}</a>
                        @endif
                    @else
                        <a href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                            class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">{{ __('Logout') }}</a>
                    @endguest
                </div>
            </div>
        </nav>

        @auth
            <div class="bg-gradient-to-r from-blue-500 to-purple-500 shadow-md">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between">
                        <div class="hidden sm:flex sm:space-x-8 py-3">
                            <a href="{{ route('home') }}"
                                class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                            <a href="{{ route('tickets.index') }}"
                                class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Tickets</a>
                            <a href="{{ route('departamentos.index') }}"
                                class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Departamentos</a>
                            <a href="{{ route('estados.index') }}"
                                class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Estados</a>
                            <a href="{{ route('reportes.index') }}"
                                class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Reportes</a>
                        </div>
                        <div class="sm:hidden">
                            <button @click="open = !open"
                                class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">
                                Menú
                                <svg class="inline-block w-4 h-4 ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div v-show="open" class="sm:hidden py-2">
                        <a href="{{ route('home') }}"
                            class="block text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="{{ route('tickets.index') }}"
                            class="block text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Tickets</a>
                        <a href="{{ route('departamentos.index') }}"
                            class="block text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Departamentos</a>
                        <a href="{{ route('estados.index') }}"
                            class="block text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Estados</a>
                        <a href="{{ route('reportes.index') }}"
                            class="block text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">Reportes</a>
                    </div>
                </div>
            </div>
        @endauth

        <main class="flex-grow py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>

        <footer class="bg-white shadow-md mt-8">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-gray-500">
                    &copy; {{ date('Y') }} {{ config('app.name', 'RM Consuegra SRL Soporte') }}. Todos los
                    derechos reservados.
                </p>
            </div>
        </footer>
    </div>
    @stack('scripts')
</body>

</html>
