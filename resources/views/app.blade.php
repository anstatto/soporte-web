<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f1419">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Soporte">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/images/LogoMono.png">
    <link rel="icon" href="/images/LogoMono.png" type="image/png">
    <title inertia>{{ config('app.name', 'RM Consuegra Soporte') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased bg-[#0f1419] text-[#E8EEF4]">
    @inertia
</body>
</html>
