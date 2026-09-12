@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'required' => false,
    'disabled' => false,
    'placeholder' => 'Select an option',
    'value' => null,
    'help' => null,
    'inline' => false,
])

@php
$attrs = ($attributes ?? new \Illuminate\View\ComponentAttributeBag);
$errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
$hasError = $name && $errors->has($name);
$rawValue = $value ?? $attrs->get('selected');
$selectedValue = $name ? old($name, $rawValue instanceof \BackedEnum ? $rawValue->value : $rawValue) : $rawValue;
@endphp

<div class="{{ $inline ? '' : 'mb-4' }}">
    @if($label)
        <label for="{{ $name ?? $attrs->whereStartsWith('id')->first() }}"
               class="block text-sm font-medium text-ink">
            {{ $label }}
            @if($required) <span class="text-danger">*</span> @endif
        </label>
    @endif

    <select @if($name) name="{{ $name }}" id="{{ $name }}" @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attrs->except(['label', 'name', 'options', 'required', 'disabled', 'placeholder', 'value', 'selected', 'help', 'inline']) }}
            class="mt-1 w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg
                   focus:bg-surface text-ink
                   focus:outline-none focus:ring-2 focus:ring-primary
                   disabled:bg-canvas-subtle disabled:text-ink-muted
                   @if($hasError) border-danger @endif
                   {{ $attrs->get('class', '') }}">
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($selectedValue == $optValue)>
                {{ $optLabel }}
            </option>
        @endforeach
    </select>

    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif

    @if($name)
        @error($name)
            <p class="mt-1 text-xs text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>
