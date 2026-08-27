@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
$types = [
    'success' => 'bg-success-subtle border-success-border text-success-text',
    'error' => 'bg-danger-subtle border-danger-border text-danger-text',
    'warning' => 'bg-warning-subtle border-warning-border text-warning-text',
    'info' => 'bg-info-subtle border-info-border text-info-text',
];
$icons = [
    'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'error' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
];
@endphp

<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'flex items-start gap-3 rounded-md border p-4 ' . $types[$type], 'role' => 'alert']) }}>
    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$type] }}" />
    </svg>
    <div class="flex-1">
        @if($title)
            <p class="font-medium">{{ $title }}</p>
        @endif
        <p class="text-sm">{{ $slot }}</p>
    </div>
    @if($dismissible)
        <button type="button" @click="$el.parentElement.remove()" class="text-current opacity-50 hover:opacity-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    @endif
</div>
