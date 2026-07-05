<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'IVE · Infrastructure Visualization Engine')</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; overflow: hidden; background: #f8fafc; }
    </style>

    {{--
        Layout autónomo — sin sidebar, sin topbar Laravel.
        Solo el canvas IVE ocupa 100 vw × 100 vh.

        @viteReactRefresh DEBE ir antes del bundle React para que
        el Fast Refresh preamble esté disponible en dev.
    --}}
    @viteReactRefresh
    @vite('resources/js/ive/main.jsx')
</head>
<body>
    @yield('content')
</body>
</html>
