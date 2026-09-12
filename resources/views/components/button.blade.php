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
$baseClass = 'inline-flex items-center gap-2 justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-wait';

$variantClass = match($variant) {
    'primary' => 'bg-primary text-on-primary hover:bg-primary-hover',
    'secondary' => 'bg-surface border border-border text-ink hover:bg-canvas-subtle',
    'danger' => 'bg-danger text-on-danger hover:bg-danger-hover',
    'success' => 'bg-success text-on-success hover:bg-success-hover',
    'warning' => 'bg-warning text-on-warning hover:bg-warning-hover',
    'info' => 'bg-info text-on-info hover:bg-info-hover',
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
$attrs = ($attributes ?? new \Illuminate\View\ComponentAttributeBag);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attrs->merge(['class' => $classes]) }} {{ $disabled ? 'aria-disabled="true"' : '' }}>
        @if($icon && !$loading)
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}" />
            </svg>
        @endif
        @if($loading)
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attrs->merge(['class' => $classes]) }} {{ $disabled ? 'disabled' : '' }} {{ $loading ? 'disabled' : '' }}>
        @if($icon && !$loading)
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}" />
            </svg>
        @endif
        @if($loading)
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
