{{-- Alert Component --}}
@props(['type' => 'info', 'title' => ''])

@php
$classes = match($type) {
    'success' => 'bg-[--color-success] text-white',
    'error' => 'bg-[--color-danger] text-white',
    'warning' => 'bg-[--color-warning] text-white',
    default => 'bg-[--color-accent] text-white',
};
@endphp

<div class="rounded-xl p-4 mb-6 animate-slideDown {{ $classes }}">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            @if($type === 'success')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            @elseif($type === 'error')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            @elseif($type === 'warning')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            @else
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            @endif
        </svg>
        <div class="flex-1">
            @if($title)
            <p class="font-medium mb-1">{{ $title }}</p>
            @endif
            <div class="text-sm opacity-90">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>