<x-app-layout title="{{ ucfirst(str_replace('/', ' ', rates/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', rates/index)) }}" description="Management" />
    <x-card>
        <p class="text-ink-muted">{{ rates/index }} view content.</p>
    </x-card>
</x-app-layout>
