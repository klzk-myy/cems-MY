@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
])

@php
$variants = [
    'primary' => 'bg-primary text-on-primary hover:bg-primary-hover',
    'secondary' => 'bg-surface border border-border text-ink hover:bg-surface-subtle',
    'danger' => 'bg-danger text-on-danger hover:bg-danger-hover',
    'success' => 'bg-success text-on-success hover:bg-success-hover',
    'warning' => 'bg-warning text-on-warning hover:bg-warning-hover',
    'info' => 'bg-info text-on-info hover:bg-info-hover',
    'ghost' => 'text-ink hover:bg-surface-subtle',
];
$sizes = [
    'sm' => 'px-3 py-1.5 text-xs',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-6 py-3 text-base',
];
$classes = 'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none ' . $variants[$variant] . ' ' . $sizes[$size];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>
