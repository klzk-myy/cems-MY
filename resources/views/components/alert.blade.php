@props(['type' => 'info', 'title' => null, 'dismissible' => false])

@php
$styles = match($type) {
    'success' => 'bg-success-subtle border-success-border text-success-text',
    'error' => 'bg-danger-subtle border-danger-border text-danger-text',
    'warning' => 'bg-warning-subtle border-warning-border text-warning-text',
    'info' => 'bg-info-subtle border-info-border text-info-text',
    default => 'bg-canvas-subtle border-border text-ink-muted',
};

$icons = [
    'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'error' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
];
@endphp

<div x-data="{ shown: true }" 
     x-show="shown"
     {{ $attributes->merge(['class' => "mb-6 border rounded-lg p-4 $styles"]) }}
     x-transition>
    <div class="flex gap-3">
        @if(isset($icon) && $icon !== false)
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$type] }}" />
            </svg>
        @endif
        
        <div class="flex-1">
            @if($title)
                <p class="font-medium mb-1">{{ $title }}</p>
            @endif
            <div class="text-sm">{{ $slot }}</div>
        </div>
        
        @if($dismissible)
            <button @click="shown = false" 
                    class="shrink-0 -mr-1 -mt-1 p-1 rounded hover:bg-black/5 dark:hover:bg-surface/10">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>
</div>