@props([
    'name',
    'label' => null,
    'checked' => false,
])

<label class="inline-flex items-center gap-2">
    <input
        type="checkbox"
        name="{{ $name }}"
        @if($checked) checked @endif
        {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-border bg-canvas-subtle text-primary focus:ring-primary']) }}
    />
    @if($label)
        <span class="text-sm text-ink">{{ $label }}</span>
    @endif
</label>
