@props(['value' => 0, 'color' => null, 'max' => 100, 'size' => 'md', 'width' => 'w-20'])

@php
$percent = min(($value / $max) * 100, 100);
$colorClass = $color ?? ($percent >= 100 ? 'bg-danger' : ($percent >= 80 ? 'bg-warning' : 'bg-success'));
$sizeClass = match($size) {
    'sm' => 'h-1',
    'md' => 'h-2',
    'lg' => 'h-3',
    default => 'h-2',
};
@endphp

<div {{ $attributes->merge(['class' => "$width bg-canvas-subtle rounded-full overflow-hidden"]) }}>
    <div class="{{ $colorClass }} {{ $sizeClass }} rounded-full transition-all duration-300" style="width: {{ $percent }}%"></div>
</div>