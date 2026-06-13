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
               class="block text-sm font-medium text-gray-700 mb-2">
            {{ $label }}
            @if($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif
    
    <select @if($name) name="{{ $name }}" id="{{ $name }}" @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->except(['label', 'name', 'options', 'required', 'disabled', 'placeholder', 'help', 'inline']) }}
            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg 
                   focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black
                   disabled:bg-gray-50 disabled:text-gray-500
                   @error($name ?? '') border-red-500 @enderror
                   {{ $attributes->get('class', '') }}">
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $value => $label)
            <option value="{{ $value }}" @selected(old($name ?? '', $attributes->get('selected')) == $value)>
                {{ $label }}
            </option>
        @endforeach
    </select>
    
    @if($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    
    @if($name)
        @error($name)
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>