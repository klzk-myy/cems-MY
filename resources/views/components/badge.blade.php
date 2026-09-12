@props([
    'variant' => 'gray',
    'size' => 'md',
])

@php
// 'error' is an alias for 'danger' styling, matching the same alias in the
// alert component.
$styles = match($variant) {
    'success' => 'bg-success-subtle text-success-text',
    'danger', 'error' => 'bg-danger-subtle text-danger-text',
    'warning' => 'bg-warning-subtle text-warning-text',
    'info' => 'bg-info-subtle text-info-text',
    'purple' => 'bg-accent/10 text-accent',
    'gray' => 'bg-canvas-subtle text-ink-muted',
    default => 'bg-canvas-subtle text-ink-muted',
};

$sizeClass = match($size) {
    'sm' => 'px-2 py-0.5 text-xs',
    'lg' => 'px-3 py-1 text-sm',
    default => 'px-2 py-0.5 text-xs',
};
@endphp

<span {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => "inline-flex items-center rounded $styles $sizeClass font-medium"]) }}>
    {{ $slot }}
</span>
