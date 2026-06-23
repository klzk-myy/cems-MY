@props(['color' => 'info', 'size' => 'md'])

@php
$colorClass = match($color) {
    'info' => 'bg-info-subtle text-info',
    'success' => 'bg-success-subtle text-success-text',
    'danger' => 'bg-danger-subtle text-danger-text',
    'warning' => 'bg-warning-subtle text-warning-text',
    default => 'bg-canvas-subtle text-ink-muted',
};

$sizeClass = match($size) {
    'sm' => 'w-8 h-8',
    'md' => 'w-10 h-10',
    'lg' => 'w-12 h-12',
    default => 'w-10 h-10',
};
@endphp

<div {{ $attributes->merge(['class' => "{$sizeClass} {$colorClass} rounded-full flex items-center justify-center"]) }}>
    {{ $slot }}
</div>
