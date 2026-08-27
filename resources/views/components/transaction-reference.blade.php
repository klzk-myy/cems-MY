@props([
    'reference',
])

<span {{ $attributes->merge(['class' => 'font-mono text-sm text-ink']) }}>
    {{ $reference }}
</span>
