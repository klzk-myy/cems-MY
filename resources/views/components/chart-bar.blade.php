@props(['value' => 0, 'color' => null, 'minHeight' => 5])

@php
$height = max($value, $minHeight);
$colorClass = $color ?? ($value >= 80 ? 'bg-green-500' : ($value >= 50 ? 'bg-yellow-500' : 'bg-red-500'));
@endphp

<div {{ $attributes->merge(['class' => 'w-full rounded-t relative']) }} style="height: {{ $height }}%">
    <div class="absolute inset-0 {{ $colorClass }} rounded-t"></div>
</div>