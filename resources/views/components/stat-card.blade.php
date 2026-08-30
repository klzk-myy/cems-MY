@props([
    'label',
    'value',
    'color' => 'gray',
    'trend' => null,
])

@php
$colors = [
    'blue' => 'text-info',
    'red' => 'text-danger',
    'yellow' => 'text-warning',
    'purple' => 'text-accent',
    'green' => 'text-success',
    'gray' => 'text-ink-muted',
];
$trendColor = $trend > 0 ? 'text-success-text' : 'text-danger';
@endphp

<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'rounded-xl border border-border bg-surface p-5']) }}>
    <p class="text-sm text-ink-muted">{{ $label }}</p>
    <p class="mt-1 text-2xl font-bold {{ $colors[$color] }}">@if(trim((string) ($slot ?? '')) !== ''){{ $slot }}@else{{ $value }}@endif</p>
    @if($trend !== null)
        <p class="mt-1 text-xs {{ $trendColor }}">
            {{ $trend > 0 ? '+' : '' }}{{ $trend }}%
        </p>
    @endif
</div>
