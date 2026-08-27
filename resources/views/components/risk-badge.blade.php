@props([
    'level' => 'low',
])

@php
$levels = [
    'low' => 'bg-success-subtle text-success-text',
    'medium' => 'bg-warning-subtle text-warning-text',
    'high' => 'bg-danger-subtle text-danger-text',
    'critical' => 'bg-danger text-on-danger',
];
@endphp

<span {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ' . $levels[$level]]) }}>
    {{ ucfirst($level) }}
</span>
