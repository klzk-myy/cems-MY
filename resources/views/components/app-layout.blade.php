@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-canvas-subtle text-ink antialiased font-sans" {{ $attributes ?? '' }}>
    <div class="flex h-full" x-data="{ sidebarCollapsed: false }">
        <x-navigation :collapsible="true" :collapsed="false" />

        <main class="flex-1 overflow-y-auto">
            <div class="mx-auto max-w-7xl p-6">
                @if(session('success'))
                    <x-alert type="success" :dismissible="true" class="mb-4">{{ session('success') }}</x-alert>
                @endif
                @if(session('error'))
                    <x-alert type="error" :dismissible="true" class="mb-4">{{ session('error') }}</x-alert>
                @endif
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
