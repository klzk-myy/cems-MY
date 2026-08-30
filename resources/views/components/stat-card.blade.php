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

{{-- Note: Add basic dark mode support (O9) — e.g. dark:text-ink-dark, dark:bg-card-dark --}}
<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'rounded-xl border border-border bg-surface p-5 dark:bg-card-dark dark:border-border-dark']) }}>
    <p class="text-sm text-ink-muted dark:text-ink-dark">{{ $label }}</p>
    <p class="mt-1 text-2xl font-bold {{ $colors[$color] }} dark:text-ink-dark">@if(trim((string) ($slot ?? '')) !== ''){{ $slot }}@else{{ $value }}@endif</p>
    @if($trend !== null)
        <p class="mt-1 text-xs {{ $trendColor }} dark:text-ink-muted">
            {{ $trend > 0 ? '+' : '' }}{{ $trend }}%
        </p>
    @endif
</div>
