@props([
    'title' => '',
    'description' => null,
    'actions' => null,
])

<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'bg-surface border border-border rounded-xl shadow-sm overflow-hidden']) }}>
    @if($title || $description || $actions)
        <div class="px-5 py-3 border-b border-border flex items-start justify-between gap-4">
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
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
</div>
