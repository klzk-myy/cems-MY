@props(['color' => 'gray', 'size' => 'sm'])

@php
$colorClass = match($color) {
    'success' => 'bg-success',
    'danger' => 'bg-danger',
    'warning' => 'bg-warning',
    'info' => 'bg-info',
    default => 'bg-canvas-subtle',
};

$sizeClass = match($size) {
    'sm' => 'w-2 h-2',
    'md' => 'w-3 h-3',
    'lg' => 'w-4 h-4',
    default => 'w-3 h-3',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-block rounded-full {$sizeClass} {$colorClass}"]) }}></span>
