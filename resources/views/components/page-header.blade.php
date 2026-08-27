@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex items-center justify-between']) }}>
    <div>
        <h1 class="text-2xl font-bold text-ink">{{ $title }}</h1>
        @if($description)
            <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</div>
