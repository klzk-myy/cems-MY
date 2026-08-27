@props([
    'title',
    'labels' => [],
    'values' => [],
    'color' => 'danger',
])

@php
$colors = [
    'success' => ['fill-success', 'text-success'],
    'warning' => ['fill-warning', 'text-warning'],
    'danger' => ['fill-danger', 'text-danger'],
];
$max = max($values) ?: 1;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-surface p-5']) }}>
    <h4 class="text-sm font-medium text-ink">{{ $title }}</h4>
    <div class="mt-4 flex h-32 items-end gap-1">
        @foreach($values as $i => $value)
            <div class="flex-1 rounded-t {{ $colors[$color][0] }}" style="height: {{ ($value / $max) * 100 }}%"></div>
        @endforeach
    </div>
    <div class="mt-2 flex justify-between text-xs text-ink-muted">
        @foreach($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
    </div>
</div>
