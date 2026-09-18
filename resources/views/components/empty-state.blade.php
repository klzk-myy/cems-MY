@props([
    'as' => 'div',
    'title' => null,
    'description' => null,
    'message' => null,
])

<{{ $as }} {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-surface p-12 text-center']) }}>
    <svg class="h-12 w-12 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
    </svg>
    @if($title ?? $message)
        <p class="mt-4 text-sm font-medium text-ink">{{ $title ?? $message }}</p>
    @endif
    @if($description)
        <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-4">
            {{ $actions }}
        </div>
    @endisset
    @isset($slot)
        @if(! ($slot instanceof \Illuminate\View\ComponentSlot ? $slot->isEmpty() : trim((string) $slot) === ''))
            <div class="mt-4">
                {{ $slot }}
            </div>
        @endif
    @endisset
</{{ $as }}>
