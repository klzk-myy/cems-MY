@props([
    'label' => null,
    'rowClass' => null,
    'valueClass' => 'font-medium text-ink',
])

<div @class([$rowClass])>
    <dt class="text-ink-muted">{{ $label }}</dt>
    @if($valueClass)
        <dd class="{{ $valueClass }}">{{ $slot }}</dd>
    @else
        <dd>{{ $slot }}</dd>
    @endif
</div>
