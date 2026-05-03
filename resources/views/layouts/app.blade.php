<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' - ' . config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100">
    <div id="app" class="flex">
        @auth
            <x-navigation />
            <div class="flex-1">
                <main class="p-6">
                    {{ $slot }}
                </main>
            </div>
        @else
            <main class="flex-1">
                {{ $slot }}
            </main>
        @endauth
    </div>
</body>
</html>