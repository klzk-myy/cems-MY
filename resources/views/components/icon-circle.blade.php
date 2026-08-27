@props([
    'color' => 'info',
])

@php
$colors = [
    'success' => 'bg-success-subtle text-success-text',
    'error' => 'bg-danger-subtle text-danger-text',
    'warning' => 'bg-warning-subtle text-warning-text',
    'info' => 'bg-info-subtle text-info-text',
    'gray' => 'bg-canvas-subtle text-ink-muted',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex h-8 w-8 items-center justify-center rounded-full ' . $colors[$color]]) }}>
    {{ $slot }}
</span>
