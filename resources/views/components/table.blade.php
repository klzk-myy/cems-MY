<@props([
    'hover' => true,
    'striped' => false
])

<table class="min-w-full divide-y divide-[#e5e5e5]">
    <thead class="bg-gray-50">
        <tr>
            {{ $thead }}
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-[#e5e5e5]">
        {{ $tbody }}
    </tbody>
</table>