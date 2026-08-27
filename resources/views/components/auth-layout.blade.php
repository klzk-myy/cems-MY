<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-canvas-subtle text-ink antialiased font-sans">
    <div class="flex min-h-full items-center justify-center px-4">
        <div class="w-full max-w-md">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
