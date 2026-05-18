<@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button'
])

@php
$baseClass = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors';

$variantClass = match($variant) {
    'primary' => 'bg-[#0a0a0a] text-white hover:bg-[#262626]',
    'secondary' => 'bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50',
    'danger' => 'bg-red-600 text-white hover:bg-red-700',
    'success' => 'bg-green-600 text-white hover:bg-green-700',
    default => 'bg-[#0a0a0a] text-white hover:bg-[#262626]',
};

$sizeClass = match($size) {
    'sm' => 'px-3 py-1.5 text-xs',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-6 py-3 text-base',
    default => 'px-4 py-2 text-sm',
};

$classes = trim("$baseClass $variantClass $sizeClass");
@endphp

@if($href)
    <a href="{{ $href }}" class="{{ $classes }}">
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" class="{{ $classes }}">
        {{ $slot }}
    </button>
@endif