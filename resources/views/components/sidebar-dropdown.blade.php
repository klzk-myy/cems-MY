<div x-data="{ open: false }">
    <button
        @click="open = !open"
        class="nav-section-title w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-[--sidebar-text-muted] uppercase tracking-wider hover:text-[--sidebar-text] transition-colors"
    >
        <span class="flex items-center gap-2">
            <x-icon name="{{ $icon }}" class="w-4 h-4" />
            {{ $title }}
        </span>
        <svg :class="{ 'rotate-180': open }" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    <div x-show="open" x-collapse class="space-y-1 px-2 py-1">
        {{ $slot }}
    </div>
</div>