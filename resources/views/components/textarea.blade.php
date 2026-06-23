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

<div class="{{ $inline ? '' : 'mb-4' }}">
    @if($label)
        <label for="{{ $name ?? $attributes->whereStartsWith('id')->first() }}"
               class="block text-sm font-medium text-ink-muted mb-2">
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
        {{ $attributes->except(['label', 'name', 'required', 'disabled', 'readonly', 'placeholder', 'help', 'rows', 'inline']) }}
        class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg
               text-ink placeholder:text-ink-muted/50
               focus:outline-none focus:ring-2 focus:ring-primary/10 focus:border-primary
               disabled:bg-canvas-subtle disabled:text-ink-muted
               @if(isset($errors) && $errors->has($name ?? '')) border-danger @endif
               {{ $attributes->get('class', '') }}">{{ $slot }}</textarea>

    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>
