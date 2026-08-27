@props([
    'label' => null,
    'required' => false,
])

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-4 md:grid-cols-2']) }}>
    {{ $slot }}
</div>
