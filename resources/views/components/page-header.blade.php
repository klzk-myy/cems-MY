@props([
    'title',
    'description' => null,
])

<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'mb-6 flex items-center justify-between']) }}>
    <div>
        <h1 class="text-2xl font-bold text-ink">{{ $title }}</h1>
        @if($description)
            <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
        @endif
        @if(trim((string) $slot) !== '')
            <div class="mt-1 text-sm text-ink-muted">{{ $slot }}</div>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</div>
