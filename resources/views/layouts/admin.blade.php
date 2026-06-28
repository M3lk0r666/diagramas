@props([
    'title' => config('app.name', 'Laravel'),
    'breadcrumbs' => [],
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Wire UI -->
    <wireui:scripts />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Line Awesome -->
    <link rel= "stylesheet" href= "/assets/lineawesome/css/line-awesome.min.css">
    <!-- Remix Icon -->
    <link rel= "stylesheet" href= "/assets/remix-icon/remixicon.css">

    <!-- Styles -->
    @livewireStyles
</head>

<body class="font-sans antialiased bg-gray-50">

    {{-- @if (isset($header))
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
    @endif --}}

    <!-- navigation -->
    @include('layouts.includes.admin.navigation')


    <!-- Sidebar -->
    @include('layouts.includes.admin.sidebar')

    <main>
        <div class="p-4 mt-14 min-h-[calc(100vh-8rem)] lg:ml-64">
            <div class="flex items-center">
                @include('layouts.includes.admin.breadcrumbs')

                <div class="mt-40">
                    <!-- Session Status -->
                    @if (session('status'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative"
                            role="alert">
                            <span class="block sm:inline">{{ session('status') }}</span>
                        </div>
                    @endif

                    <!-- Success Message -->
                    @if (session('success'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative"
                            role="alert">
                            <span class="block sm:inline">{{ session('success') }}</span>
                        </div>
                    @endif

                    <!-- Error Message -->
                    @if (session('error'))
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"
                            role="alert">
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif
                </div>

                @isset($action)
                    <div class="ml-auto">
                        {{ $action }}
                    </div>
                @endisset
            </div>
            <!-- Contenido de las vistas-->
            {{ $slot }}
        </div>
    </main>
    @stack('modals')

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/flowbite@4.0.1/dist/flowbite.min.js"></script>
    @stack('js')
</body>

</html>
