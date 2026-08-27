@props([
    'value' => 0,
])

<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'h-2 w-full overflow-hidden rounded-full bg-canvas-subtle']) }}>
    <div class="h-full rounded-full bg-primary transition-all" style="width: {{ min(100, max(0, $value)) }}%"></div>
</div>
