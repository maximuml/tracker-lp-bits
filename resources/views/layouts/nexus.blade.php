<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="nexus-theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="min-h-screen bg-nexus-bg text-nexus-text antialiased">
    <x-nexus.header :user="Auth::guard('nexus-web')->user()">
        <x-slot:actions>
            @yield('header-actions')
        </x-slot:actions>
    </x-nexus.header>

    <main class="mx-auto max-w-content flex-grow px-4 py-6">
        @yield('content')
    </main>

    <x-nexus.footer />

    @stack('body')
</body>
</html>
