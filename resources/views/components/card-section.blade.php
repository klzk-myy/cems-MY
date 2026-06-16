@props([
    'title' => '',
    'description' => null,
    'actions' => null
])

<div {{ $attributes->merge(['class' => "bg-surface border border-border rounded-xl overflow-hidden"]) }}>
    <div class="px-6 py-4 border-b border-border flex items-start justify-between gap-4">
        <div>
            @if($title)
                <h3 class="text-lg font-semibold text-ink">{{ $title }}</h3>
            @endif
            @if($description)
                <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
            @endif
        </div>
        @if($actions)
            <div class="flex items-center gap-2 shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>
    <div>
        {{ $slot }}
    </div>
</div>