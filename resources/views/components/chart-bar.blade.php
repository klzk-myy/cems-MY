@props([
    'value' => 0,
])

@php
$color = $value >= 75 ? 'bg-success' : ($value >= 50 ? 'bg-warning' : 'bg-danger');
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    <div class="flex items-center justify-between text-xs text-ink-muted">
        <span>{{ $slot }}</span>
        <span>{{ $value }}%</span>
    </div>
    <div class="h-2 w-full overflow-hidden rounded-full bg-canvas-subtle">
        <div class="h-full rounded-full {{ $color }}" style="width: {{ $value }}%"></div>
    </div>
</div>
