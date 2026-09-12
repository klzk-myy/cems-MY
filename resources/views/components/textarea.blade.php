@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'placeholder' => null,
    'help' => null,
    'rows' => 3,
    'inline' => false,
])

@php
$attrs = ($attributes ?? new \Illuminate\View\ComponentAttributeBag);
@endphp

<div class="{{ $inline ? '' : 'mb-4' }}">
    @if($label)
        <label for="{{ $name ?? $attrs->whereStartsWith('id')->first() }}"
               class="block text-sm font-medium text-ink">
            {{ $label }}
            @if($required) <span class="text-danger">*</span> @endif
        </label>
    @endif

    <textarea
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        rows="{{ $rows }}"
        {{ $attrs->except(['label', 'name', 'required', 'disabled', 'readonly', 'placeholder', 'help', 'rows', 'inline']) }}
        class="mt-1 w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg
               focus:bg-surface text-ink placeholder:text-ink-muted
               focus:outline-none focus:ring-2 focus:ring-primary
               disabled:bg-canvas-subtle disabled:text-ink-muted
               @if(isset($errors) && $errors->has($name ?? '')) border-danger @endif
               {{ $attrs->get('class', '') }}">{{ $slot }}</textarea>

    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-xs text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>
