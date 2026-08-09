<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="nexus-theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
    $defCss = \App\Support\SupportContext::getGlobal('defcss', 4);
    $user = \App\Support\SupportContext::getUser() ?? [];
    $userStylesheet = $user['stylesheet'] ?? $defCss;
    $css_uri = \App\Support\Style::cssUri(\App\Support\SupportContext::getCache(), $userStylesheet, $defCss);
    @endphp
    <link rel="stylesheet" href="{{ $css_uri }}theme.css" type="text/css" />
    <link rel="stylesheet" href="styles/nexus-legacy-compat.css" type="text/css" />

    <script type="text/javascript" src="js/jquery-1.12.4.min.js"></script>
    <script type="text/javascript" src="vendor/layer-v3.5.1/layer/layer.js"></script>

    @foreach (\Nexus\Nexus::getAppendHeaders() as $html)
        {!! $html !!}
    @endforeach

    @stack('head')
</head>
<body class="min-h-screen bg-nexus-bg text-nexus-text antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-nexus-primary focus:px-4 focus:py-2 focus:text-nexus-primary-text">
        Skip to main content
    </a>

    <x-nexus.header :user="Auth::guard('nexus-web')->user()">
        <x-slot:actions>
            @yield('header-actions')
        </x-slot:actions>
    </x-nexus.header>

    <main id="main-content" class="mx-auto max-w-content flex-grow overflow-x-auto px-4 py-6" role="main" aria-label="Main content" tabindex="-1">
        @yield('content')
    </main>

    <x-nexus.footer role="contentinfo" />

    @foreach (\Nexus\Nexus::getAppendFooters() as $html)
        {!! $html !!}
    @endforeach

    <script type="application/javascript" src="js/nexus.js"></script>
    <script type="application/javascript" src="js/medium-zoom.min.js"></script>
    <script type="application/javascript" src="vendor/jquery-goup-1.1.3/jquery.goup.min.js"></script>
    <script>
        jQuery(document).ready(function(){
            jQuery.goup()
            mediumZoom('[data-zoomable]')
        })
    </script>

    @stack('body')
</body>
</html>
