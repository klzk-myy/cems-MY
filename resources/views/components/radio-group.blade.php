@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'disabled' => false,
    'help' => null,
    'inline' => false,
])

<div class="{{ $inline ? '' : 'mb-4' }}">
    @if($label)
        <label class="block text-sm font-medium text-ink-muted mb-2">
            {{ $label }}
            @if($required) <span class="text-danger">*</span> @endif
        </label>
    @endif

    <div class="flex flex-wrap gap-4">
        @foreach($options as $value => $optionLabel)
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $value }}"
                    @checked(old($name, $selected) == $value)
                    @if($required) required @endif
                    @if($disabled) disabled @endif
                    {{ $attributes->except(['label', 'name', 'options', 'selected', 'required', 'disabled', 'help', 'inline']) }}
                    class="w-4 h-4 border-border text-primary focus:ring-primary focus:ring-2 disabled:opacity-50 {{ $attributes->get('class', '') }}"
                >
                <span class="text-sm text-ink">{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>

    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>
