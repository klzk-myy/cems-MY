@props([
    'count' => 0,
])

<button {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'relative rounded-md p-2 text-ink-muted hover:bg-surface-subtle hover:text-ink']) }}>
    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
    @if($count > 0)
        <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-danger text-[10px] font-bold text-on-danger">{{ $count > 9 ? '9+' : $count }}</span>
    @endif
</button>
