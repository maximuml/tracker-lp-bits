<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">

        <title>@yield('title')</title>

        <style>
            html{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif;line-height:1.5}body{margin:0;font-family:inherit}.flex{display:flex}.items-center{align-items:center}.justify-center{justify-content:center}.min-h-screen{min-height:100vh}.mx-auto{margin-left:auto;margin-right:auto}.max-w-xl{max-width:36rem}.border-r{border-right-width:1px}.px-4{padding-left:1rem;padding-right:1rem}.ml-4{margin-left:1rem}.pt-8{padding-top:2rem}.text-lg{font-size:1.125rem}.antialiased{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
            body{color:#1a202c;background-color:#f7fafc}.code{border-color:#cbd5e0}
            @media (prefers-color-scheme:dark){body{color:#e2e8f0;background-color:#1a202c}.code{border-color:#4a5568}}
            @media (min-width:640px){.sm\:items-center{align-items:center}.sm\:pt-0{padding-top:0}.sm\:px-6{padding-left:1.5rem;padding-right:1.5rem}}
        </style>
    </head>
    <body class="antialiased">
        <div class="flex justify-center items-center min-h-screen sm:items-center sm:pt-0" role="main">
            <div class="max-w-xl mx-auto sm:px-6">
                <div class="flex items-center pt-8 sm:pt-0">
                    <h1 class="px-4 text-lg border-r code">
                        @yield('code')
                    </h1>

                    <div class="ml-4 text-lg">
                        @yield('message')
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
