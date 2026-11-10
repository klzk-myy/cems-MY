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
    <div class="flex min-h-full" x-data="appShell">
        <x-navigation :collapsible="true" :collapsed="false" />

        {{-- Scrim behind the off-canvas sidebar on small screens --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             @keydown.escape.window="sidebarOpen = false"
             x-transition.opacity
             class="fixed inset-0 z-30 bg-black/50 md:hidden"
             aria-hidden="true"></div>

        <main id="main-content" class="flex-1 flex flex-col min-w-0">
            @auth
                <header class="h-14 bg-surface border-b border-border px-4 md:px-6 flex items-center justify-between gap-3 shrink-0">
                    <button type="button"
                            @click="sidebarOpen = true"
                            aria-label="Open navigation"
                            class="rounded-md p-2 text-ink-muted hover:bg-canvas-subtle hover:text-ink md:hidden">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="ml-auto flex items-center gap-3">
                        <x-notification-bell
                            :unread-notifications="$unreadNotifications ?? []"
                            :unread-count="$unreadNotificationCount ?? 0"
                            :dlq-count="$headerDlqCount ?? 0"
                        />
                        <x-user-menu />
                    </div>
                </header>
            @endauth

            <div class="mx-auto p-6 w-full flex-1">
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

    <x-confirm-dialog />
</body>
</html>
