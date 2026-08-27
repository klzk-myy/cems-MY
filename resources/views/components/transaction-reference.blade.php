@props([
    'reference',
])

<span {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'font-mono text-sm text-ink']) }}>
    {{ $reference }}
</span>
