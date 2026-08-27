@props([
    'variant' => 'gray',
])

@php
$variants = [
    'success' => 'bg-success-subtle text-success-text',
    'error' => 'bg-danger-subtle text-danger-text',
    'warning' => 'bg-warning-subtle text-warning-text',
    'info' => 'bg-info-subtle text-info-text',
    'purple' => 'bg-accent/10 text-accent',
    'gray' => 'bg-canvas-subtle text-ink-muted',
];
@endphp

<span {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ' . $variants[$variant]]) }}>
    {{ $slot }}
</span>
