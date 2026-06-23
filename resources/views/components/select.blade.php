@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'required' => false,
    'disabled' => false,
    'placeholder' => 'Select an option',
    'help' => null,
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
    
    <select @if($name) name="{{ $name }}" id="{{ $name }}" @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->except(['label', 'name', 'options', 'required', 'disabled', 'placeholder', 'help', 'inline']) }}
            class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg 
                   text-ink
                   focus:outline-none focus:ring-2 focus:ring-primary/10 focus:border-primary
                   disabled:bg-canvas-subtle disabled:text-ink-muted
                   @if(isset($errors) && $errors->has($name ?? '')) border-danger @endif
                   {{ $attributes->get('class', '') }}">
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $value => $label)
            <option value="{{ $value }}" @selected(old($name ?? '', $attributes->get('selected')) == $value)>
                {{ $label }}
            </option>
        @endforeach
    </select>
    
    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif
    
    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>