@props(['cols' => 4])

@php
$gridClasses = match($cols) {
    1 => 'grid-cols-1',
    2 => 'grid-cols-1 sm:grid-cols-2',
    3 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    6 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6',
    default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
};
@endphp

<div class="grid {{ $gridClasses }} gap-4">
    {{ $slot }}
</div>