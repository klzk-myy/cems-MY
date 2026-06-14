@props(['title', 'labels', 'values', 'color' => 'red'])

@php
$colorClass = match ($color) {
    'yellow' => 'fill-yellow-500',
    'green' => 'fill-green-500',
    default => 'fill-red-500',
};
$textClass = match ($color) {
    'yellow' => 'text-yellow-600',
    'green' => 'text-green-600',
    default => 'text-red-600',
};
$firstValue = $values[0] ?? 0;
$lastValue = $values[count($values) - 1] ?? 0;
$change = $firstValue > 0 ? round((($lastValue - $firstValue) / $firstValue) * 100, 0) : 0;
$changeLabel = $change >= 0 ? 'increase' : 'decrease';
@endphp

<div {{ $attributes->merge(['class' => 'bg-surface border border-border rounded-xl p-6']) }}>
    <h3 class="text-lg font-semibold text-ink mb-4">{{ $title }}</h3>
    <div class="h-48 flex items-end justify-between gap-2">
        @foreach($labels as $index => $label)
            @php
                $value = $values[$index] ?? 0;
                $maxValue = max($values) ?: 1;
                $barHeight = min(($value / $maxValue) * 100, 100);
            @endphp
            <div class="flex-1 flex flex-col items-center justify-end h-full">
                <span class="text-xs font-medium text-ink-muted mb-1">{{ $value }}</span>
                <svg class="w-full" style="height: {{ $barHeight }}%;" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <rect x="15" y="0" width="70" height="100" rx="4" class="{{ $colorClass }}"></rect>
                </svg>
                <span class="mt-2 text-xs text-ink-muted">{{ $label }}</span>
            </div>
        @endforeach
    </div>
    <div class="mt-4 flex items-center justify-between text-sm">
        <span class="text-ink-muted">{{ $labels[0] ?? '' }}: {{ $firstValue }}</span>
        <span class="{{ $textClass }} font-medium">{{ $change >= 0 ? '+' : '' }}{{ $change }}% {{ $changeLabel }}</span>
        <span class="text-ink-muted">{{ $labels[count($labels) - 1] ?? '' }}: {{ $lastValue }}</span>
    </div>
</div>
