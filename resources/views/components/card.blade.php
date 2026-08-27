@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-surface']) }}>
    @if($title || $description)
        <div class="border-b border-border px-5 py-4">
            @if($title)
                <h3 class="text-base font-semibold text-ink">{{ $title }}</h3>
            @endif
            @if($description)
                <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
</div>
