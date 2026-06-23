@props([
    'title' => '',
    'description' => null,
    'actions' => null
])

<div {{ $attributes->merge(['class' => "flex items-center justify-between"]) }}>
    <div>
        @if($title)
            <h1 class="text-2xl font-semibold text-ink">{{ $title }}</h1>
        @endif
        @if($description)
            <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
        @endif
        @if($slot)
            <p class="mt-1 text-sm text-ink-muted">{{ $slot }}</p>
        @endif
    </div>
    @if($actions)
        <div class="flex items-center gap-3">
            {{ $actions }}
        </div>
    @endif
</div>