@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'loading' => false,
    'icon' => null,
])

@php
$baseClass = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary/10 disabled:opacity-50 disabled:cursor-not-allowed';

$variantClass = match($variant) {
    'primary' => 'bg-primary text-on-primary hover:bg-primary-hover',
    'secondary' => 'bg-surface border border-border text-ink-muted hover:bg-canvas-subtle',
    'danger' => 'bg-danger text-on-danger hover:bg-danger-hover',
    'success' => 'bg-success text-white hover:bg-green-700',
    'warning' => 'bg-warning text-white hover:bg-yellow-600',
    'info' => 'bg-info text-white hover:bg-blue-700',
    'indigo' => 'bg-indigo-600 text-white hover:bg-indigo-700',
    'purple' => 'bg-purple-600 text-white hover:bg-purple-700',
    'teal' => 'bg-teal-600 text-white hover:bg-teal-700',
    'ghost' => 'bg-transparent text-ink-muted hover:bg-canvas-subtle',
    default => 'bg-primary text-on-primary hover:bg-primary-hover',
};

$sizeClass = match($size) {
    'sm' => 'px-3 py-1.5 text-xs',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-6 py-3 text-base',
    default => 'px-4 py-2 text-sm',
};

$classes = trim("$baseClass $variantClass $sizeClass");
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} {{ $disabled ? 'aria-disabled="true"' : '' }}>
        @if($icon && !$loading)
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}" />
            </svg>
        @endif
        @if($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} {{ $disabled ? 'disabled' : '' }} {{ $loading ? 'disabled' : '' }}>
        @if($icon && !$loading)
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}" />
            </svg>
        @endif
        @if($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif