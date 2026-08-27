<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Error' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-canvas-subtle text-ink antialiased font-sans">
    <div class="flex min-h-screen items-center justify-center">
        <div class="text-center">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
