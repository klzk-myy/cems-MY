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
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-[100] focus:top-2 focus:left-2 focus:px-4 focus:py-2 focus:bg-surface focus:border focus:border-border focus:rounded-lg focus:text-ink focus:shadow-lg">Skip to main content</a>
    <div class="flex h-full" x-data="appShell">
        <x-navigation :collapsible="true" :collapsed="false" />

        <main id="main-content" class="flex-1 overflow-y-auto flex flex-col">
            @auth
                <header class="h-14 bg-surface border-b border-border px-6 flex items-center justify-end gap-3 shrink-0">
                    <x-notification-bell
                        :unread-notifications="$unreadNotifications ?? []"
                        :unread-count="$unreadNotificationCount ?? 0"
                        :dlq-count="$headerDlqCount ?? 0"
                    />
                </header>
            @endauth

            <div class="mx-auto max-w-7xl p-6 w-full flex-1">
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
