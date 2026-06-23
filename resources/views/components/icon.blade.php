@props(['name', 'class' => 'w-5 h-5'])

@php
$svg = match($name) {
    'check' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M5 13l4 4L19 7"></path></svg>',
    'x' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"></path></svg>',
    default => throw new \InvalidArgumentException("Unknown icon name: {$name}"),
};
@endphp

<span {!! $attributes->merge(['class' => $class]) !!}>
    {!! $svg !!}
</span>
