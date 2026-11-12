@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'placeholder' => null,
    'value' => null,
    'help' => null,
    'inline' => false,
])

@php
$attrs = ($attributes ?? new \Illuminate\View\ComponentAttributeBag);
$errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
$hasError = $name && $errors->has($name);

// Blade pre-escapes bound (`:value="..."`) and mustache (`value="{{ ... }}"`)
// string attributes before the component receives them, so the value is
// rendered unescaped below. Flashed old input is raw user data, so it is the
// one source that has to be escaped here.
$oldInput = $name ? old($name, null) : null;
$inputValue = $oldInput !== null
    ? (is_string($oldInput) ? e($oldInput) : $oldInput)
    : ($value ?? $attrs->get('value'));
@endphp

<div class="{{ $inline ? '' : 'mb-4' }}">
    @if($label)
        <label for="{{ $name ?? $attrs->whereStartsWith('id')->first() }}"
               class="block text-sm font-medium text-ink">
            {{ $label }}
            @if($required) <span class="text-danger">*</span> @endif
        </label>
    @endif

    <input type="{{ $type }}"
           @if($name) name="{{ $name }}" id="{{ $name }}" @endif
           @if(!is_null($inputValue)) value="{!! $inputValue !!}" @endif
           @if($required) required aria-required="true" @endif
           @if($disabled) disabled @endif
           @if($readonly) readonly @endif
           @if($placeholder) placeholder="{{ $placeholder }}" @endif
           @if($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
           {{ $attrs->except(['label', 'name', 'type', 'required', 'disabled', 'readonly', 'placeholder', 'value', 'help', 'inline']) }}
           class="mt-1 w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg
                  focus:bg-surface text-ink placeholder:text-ink-muted
                  focus:outline-none focus:ring-2 focus:ring-primary
                  disabled:bg-canvas-subtle disabled:text-ink-muted
                  @if($hasError) border-danger @endif
                  {{ $attrs->get('class', '') }}">

    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif

    @if($name)
        @error($name)
            <p id="{{ $name }}-error" class="mt-1 text-xs text-danger-text" role="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
