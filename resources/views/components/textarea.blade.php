@props([
    'name',
    'label' => null,
    'placeholder' => '',
    'rows' => 4,
    'required' => false,
])

@php
$errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
$hasError = $errors->has($name);
@endphp

<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'space-y-1']) }}>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    @endif
    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        placeholder="{{ $placeholder }}"
        rows="{{ $rows }}"
        @if($required) required @endif
        {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge([
            'class' => 'w-full rounded-md border bg-canvas-subtle px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary ' . ($hasError ? 'border-danger' : 'border-border'),
        ]) }}
    >{{ old($name) }}</textarea>
    @if($hasError)
        <p class="text-xs text-danger-text">{{ $errors->first($name) }}</p>
    @endif
</div>
