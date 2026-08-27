@props([
    'color' => 'gray',
])

@php
$colors = [
    'success' => 'bg-success',
    'error' => 'bg-danger',
    'warning' => 'bg-warning',
    'info' => 'bg-info',
    'gray' => 'bg-ink-muted',
];
@endphp

<span {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'inline-block h-2 w-2 rounded-full ' . $colors[$color]]) }}></span>
