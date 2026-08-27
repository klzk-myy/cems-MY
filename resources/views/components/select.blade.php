@props([
    'name',
    'label' => null,
    'options' => [],
    'placeholder' => 'Select...',
    'required' => false,
])

@php
$errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
$hasError = $errors->has($name);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    @endif
    <select
        name="{{ $name }}"
        id="{{ $name }}"
        @if($required) required @endif
        {{ $attributes->merge([
            'class' => 'w-full rounded-md border bg-canvas-subtle px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary ' . ($hasError ? 'border-danger' : 'border-border'),
        ]) }}
    >
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    @if($hasError)
        <p class="text-xs text-danger-text">{{ $errors->first($name) }}</p>
    @endif
</div>
