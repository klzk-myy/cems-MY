@props([
    'title',
    'labels' => [],
    'values' => [],
    'color' => 'danger',
])

@php
$colors = [
    'success' => ['bg-success', 'text-success'],
    'warning' => ['bg-warning', 'text-warning'],
    'danger' => ['bg-danger', 'text-danger'],
    'green' => ['bg-success', 'text-success'],
    'yellow' => ['bg-warning', 'text-warning'],
    'red' => ['bg-danger', 'text-danger'],
    'blue' => ['bg-info', 'text-info'],
    'purple' => ['bg-accent', 'text-accent'],
];
$values = $values instanceof \Illuminate\Support\Collection ? $values->toArray() : ($values ?? []);
$labels = $labels instanceof \Illuminate\Support\Collection ? $labels->toArray() : ($labels ?? []);
$max = !empty($values) ? max($values) : 1;
$max = $max > 0 ? $max : 1;
$colorSet = $colors[$color] ?? $colors['danger'];
@endphp

<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'rounded-xl border border-border bg-surface p-5']) }}>
    <h4 class="text-sm font-medium {{ $colorSet[1] }}">{{ $title }}</h4>
    @if (empty($values))
        <p class="mt-4 text-sm text-ink-muted">No data recorded for this period.</p>
    @else
        <div class="mt-4 flex h-32 items-stretch gap-1 border-b border-border">
            @foreach($values as $i => $value)
                <div class="relative min-w-0 flex-1">
                    <span class="absolute inset-x-0 top-0 text-center text-[10px] leading-none tabular-nums text-ink-muted">{{ number_format($value) }}</span>
                    <div class="absolute inset-x-0 bottom-0 rounded-t {{ $value > 0 ? $colorSet[0] : 'bg-border' }}"
                         style="height: {{ $value > 0 ? max(round(($value / $max) * 100), 4).'%' : '2px' }}"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between gap-1 text-xs text-ink-muted">
            @foreach($labels as $label)
                <span class="flex-1 truncate text-center">{{ $label }}</span>
            @endforeach
        </div>
    @endif
</div>
