@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'trend' => null,
    'color' => null,
    'prefix' => '',
    'suffix' => ''
])

@php
$valueColorClass = match($color) {
    'blue' => 'text-info',
    'red' => 'text-danger',
    'yellow' => 'text-warning',
    'purple' => 'text-accent',
    'green' => 'text-success',
    default => is_numeric($value) && $value >= 80 ? 'text-success' : (is_numeric($value) && $value >= 50 ? 'text-warning' : 'text-ink'),
};
@endphp

<div {{ $attributes->merge(['class' => "bg-surface border border-border rounded-xl p-5"]) }}>
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <p class="text-sm font-medium text-ink-muted">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $valueColorClass }}">
                @if($prefix)<span class="text-lg">{{ $prefix }}</span>@endif
                {{ $value }}
                @if($suffix)<span class="text-lg">{{ $suffix }}</span>@endif
            </p>
            @if($trend !== null)
                <p class="mt-1 text-xs {{ $trend >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $trend >= 0 ? '+' : '' }}{{ $trend }}% from last period
                </p>
            @endif
        </div>
        @if($icon)
            <div class="ml-4">{{ $icon }}</div>
        @endif
    </div>
</div>