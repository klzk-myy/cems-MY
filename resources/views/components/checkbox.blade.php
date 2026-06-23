@props([
    'label' => null,
    'name' => null,
    'value' => 1,
    'checked' => false,
    'required' => false,
    'disabled' => false,
    'help' => null,
    'inline' => false,
])

<div class="{{ $inline ? '' : 'mb-4' }}">
    <label class="flex items-center gap-2 cursor-pointer">
        <input
            type="checkbox"
            @if($name) name="{{ $name }}" id="{{ $name }}" @endif
            value="{{ $value }}"
            @if($checked) checked @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->except(['label', 'name', 'value', 'checked', 'required', 'disabled', 'help', 'inline']) }}
            class="w-4 h-4 rounded border-border text-primary focus:ring-primary focus:ring-2 disabled:opacity-50 {{ $attributes->get('class', '') }}"
        >
        @if($label)
            <span class="text-sm text-ink">{{ $label }}</span>
        @endif
    </label>

    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>
