<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'Overseas Admin Panel') }}</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    
    @filamentStyles
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="filament-body min-h-screen bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-white">
    {{ $slot }}

    @livewireScripts
    @filamentScripts
</body>
</html>
