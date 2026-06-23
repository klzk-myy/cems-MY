@props([
    'hover' => true,
    'striped' => false
])

<table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-border']) }}>
    <thead class="bg-canvas-subtle">
        <tr>
            {{ $thead }}
        </tr>
    </thead>
    <tbody class="bg-surface divide-y divide-border">
        {{ $tbody }}
    </tbody>
</table>