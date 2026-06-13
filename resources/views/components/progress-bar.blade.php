@props(['value' => 0, 'color' => null, 'max' => 100, 'size' => 'md', 'width' => 'w-20'])

@php
$percent = min(($value / $max) * 100, 100);
$colorClass = $color ?? ($percent >= 100 ? 'bg-red-500' : ($percent >= 80 ? 'bg-yellow-500' : 'bg-green-500'));
$sizeClass = match($size) {
    'sm' => 'h-1',
    'md' => 'h-2',
    'lg' => 'h-3',
    default => 'h-2',
};
@endphp

<div class="{{ $width }} bg-gray-200 rounded-full overflow-hidden">
    <div class="{{ $colorClass }} {{ $sizeClass }} rounded-full transition-all duration-300" style="width: {{ $percent }}%"></div>
</div>