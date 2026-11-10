@php
    $menuUser = auth()->user();
@endphp

{{-- Header user menu: account self-service links (password, MFA devices,
     notification preferences) plus the dark-mode toggle. Logout stays in
     the sidebar where it is always visible. --}}
<div class="relative"
     x-data="{ open: false }"
     @click.outside="open = false"
     @keydown.escape.window="open = false">
    <button type="button"
            @click="open = !open"
            aria-haspopup="menu"
            :aria-expanded="open.toString()"
            class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-canvas-subtle transition-colors focus:outline-none focus:ring-2 focus:ring-primary">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-on-primary text-xs font-semibold" aria-hidden="true">
            {{ strtoupper(substr($menuUser?->username ?? 'U', 0, 1)) }}
        </span>
        <span class="hidden sm:block">
            <span class="block text-sm font-medium leading-tight text-ink">{{ $menuUser?->username }}</span>
            <span class="block text-xs leading-tight text-ink-muted">{{ $menuUser?->role?->label() }}</span>
        </span>
        <svg class="hidden h-4 w-4 text-ink-muted sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         role="menu"
         class="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-lg border border-border bg-surface shadow-lg">
        <a href="{{ route('password.change') }}" role="menuitem"
           class="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-canvas-subtle">
            <svg class="h-4 w-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            Change password
        </a>
        <a href="{{ route('mfa.trusted-devices') }}" role="menuitem"
           class="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-canvas-subtle">
            <svg class="h-4 w-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            Security &amp; devices
        </a>
        <a href="{{ route('notifications.preferences') }}" role="menuitem"
           class="flex items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-canvas-subtle">
            <svg class="h-4 w-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            Notification preferences
        </a>

        <div class="border-t border-border" role="separator"></div>

        <button type="button" data-toggle="dark-mode" role="menuitem"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-canvas-subtle">
            <svg class="h-4 w-4 text-ink-muted dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <svg class="hidden h-4 w-4 text-ink-muted dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span class="dark:hidden">Dark mode</span>
            <span class="hidden dark:inline">Light mode</span>
        </button>

        <div class="border-t border-border" role="separator"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" role="menuitem"
                    class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-ink hover:bg-canvas-subtle">
                <svg class="h-4 w-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Logout
            </button>
        </form>
    </div>
</div>
