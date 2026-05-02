{{-- Form Field Component --}}
@props(['label', 'name', 'error' => null, 'required' => false])

<div class="mb-4">
    @if($label)
    <label for="{{ $name }}" class="block text-sm font-medium text-[--color-ink] mb-1">
        {{ $label }}
        @if($required)
        <span class="text-[--color-danger]">*</span>
        @endif
    </label>
    @endif
    {{ $slot }}
    @if($error)
    <p class="text-sm text-[--color-danger] mt-1">{{ $error }}</p>
    @endif
</div>