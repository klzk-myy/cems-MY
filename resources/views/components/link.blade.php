@props(['href' => '#', 'variant' => 'default', 'external' => false])

@php
$variantClass = match($variant) {
    'info' => 'text-info hover:text-info-hover',
    'danger' => 'text-danger hover:text-danger-hover',
    'muted' => 'text-ink-muted hover:text-ink',
    default => 'text-primary hover:text-primary-hover',
};
@endphp

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => "{$variantClass} hover:underline transition-colors"]) }}
   @if($external) target="_blank" rel="noopener" @endif>
    {{ $slot }}
</a>
