@props([
    'data' => [],
    'color' => '#3b82f6',
    'height' => 32,
])

@php
$values = array_values(array_filter($data, fn ($value) => is_numeric($value)));
$count = count($values);
$width = 120;

$max = max($values ?: [0]);
$min = min($values ?: [0]);
$span = ($max - $min) ?: 1;

$points = [];
foreach ($values as $index => $value) {
    $x = $count > 1 ? round($index / ($count - 1) * $width, 2) : 0;
    $y = round($height - ((($value - $min) / $span) * ($height - 4)) - 2, 2);
    $points[] = $x.','.$y;
}
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" style="height: {{ $height }}px" preserveAspectRatio="none" aria-hidden="true">
    @if($count > 1)
        <polyline fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ implode(' ', $points) }}" />
    @endif
</svg>
