@props([
    'title' => '',
    'description' => null
])

<div {{ $attributes->merge(['class' => "bg-surface border border-border rounded-xl overflow-hidden"]) }}>
    @if($title || $description)
        <div class="px-6 py-4 border-b border-border">
            @if($title)
                <h3 class="text-lg font-semibold text-ink">{{ $title }}</h3>
            @endif
            @if($description)
                <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div class="p-6">
        {{ $slot }}
    </div>
</div>