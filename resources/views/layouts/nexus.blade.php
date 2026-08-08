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
    <header class="border-b border-nexus-border bg-nexus-surface">
        <div class="mx-auto flex max-w-content items-center justify-between px-4 py-3">
            <a href="{{ url('/') }}" class="text-lg font-bold text-nexus-primary hover:underline">
                {{ config('app.name') }}
            </a>
            @yield('header-actions')
        </div>
    </header>

    <main class="mx-auto max-w-content px-4 py-6">
        @yield('content')
    </main>

    <footer class="border-t border-nexus-border bg-nexus-surface py-4 text-center text-sm text-nexus-muted">
        {{ __('Powered by :name', ['name' => config('app.name')]) }}
    </footer>

    @stack('body')
</body>
</html>
