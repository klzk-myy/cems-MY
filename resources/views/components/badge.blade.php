<@props([
    'variant' => 'gray',
    'size' => 'md'
])

@php
$styles = match($variant) {
    'success' => 'bg-green-100 text-green-700',
    'danger' => 'bg-red-100 text-red-700',
    'warning' => 'bg-yellow-100 text-yellow-700',
    'info' => 'bg-blue-100 text-blue-700',
    'purple' => 'bg-purple-100 text-purple-700',
    default => 'bg-gray-100 text-gray-700',
};

$sizeClass = match($size) {
    'sm' => 'px-2 py-0.5 text-xs',
    'md' => 'px-2.5 py-0.5 text-xs',
    'lg' => 'px-3 py-1 text-sm',
    default => 'px-2.5 py-0.5 text-xs',
};
@endphp

<span class="inline-flex rounded {{ $styles }} {{ $sizeClass }} font-medium">
    {{ $slot }}
</span>