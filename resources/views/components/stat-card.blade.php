@props([
    'label',
    'value',
    'color' => 'gray',
    'trend' => null,
    'href' => null,
    'sparkline' => null,
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
$displayValue = trim((string) ($slot ?? '')) !== '' ? $slot : $value;
$classes = 'rounded-xl border border-border bg-surface p-5 dark:bg-card-dark dark:border-border-dark';
@endphp

@if($href)
    <a {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => $classes.' block transition-colors hover:border-ink-muted']) }}>
        <span class="flex items-start justify-between gap-3">
            <span class="text-sm text-ink-muted dark:text-ink-dark">{{ $label }}</span>
            @if($trend !== null)
                <span class="text-xs {{ $trend > 0 ? 'text-success-text' : 'text-danger' }} dark:text-ink-muted">
                    {{ $trend > 0 ? '+' : '' }}{{ $trend }}%
                </span>
            @endif
        </span>
        <span class="mt-1 block text-2xl font-bold {{ $colors[$color] }} dark:text-ink-dark">{{ $displayValue }}</span>
        @if($sparkline !== null && count($sparkline) > 1)
            <span class="mt-3 block">
                <x-sparkline :data="$sparkline" />
            </span>
        @endif
    </a>
@else
    <div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => $classes]) }}>
        <p class="text-sm text-ink-muted dark:text-ink-dark">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold {{ $colors[$color] }} dark:text-ink-dark">{{ $displayValue }}</p>
        @if($trend !== null)
            <p class="mt-1 text-xs {{ $trend > 0 ? 'text-success-text' : 'text-danger' }} dark:text-ink-muted">
                {{ $trend > 0 ? '+' : '' }}{{ $trend }}%
            </p>
        @endif
        @if($sparkline !== null && count($sparkline) > 1)
            <div class="mt-3">
                <x-sparkline :data="$sparkline" />
            </div>
        @endif
    </div>
@endif
