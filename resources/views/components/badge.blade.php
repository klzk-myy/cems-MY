@props([
    'variant' => 'gray',
    'size' => 'md'
])

@php
$styles = match($variant) {
    'success' => 'bg-success-subtle text-success-text',
    'danger' => 'bg-danger-subtle text-danger-text',
    'warning' => 'bg-warning-subtle text-warning-text',
    'info' => 'bg-info-subtle text-info-text',
    'gray' => 'bg-canvas-subtle text-ink-muted',
    'purple' => 'bg-accent/10 text-accent',
    default => 'bg-canvas-subtle text-ink-muted',
};

$sizeClass = match($size) {
    'sm' => 'px-2 py-0.5 text-xs',
    'md' => 'px-2.5 py-0.5 text-xs',
    'lg' => 'px-3 py-1 text-sm',
    default => 'px-2.5 py-0.5 text-xs',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded $styles $sizeClass font-medium"]) }}>
    {{ $slot }}
</span>