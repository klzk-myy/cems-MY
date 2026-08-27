<x-app-layout title="{{ ucfirst(str_replace('/', ' ', performance/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', performance/index)) }}" description="Management" />
    <x-card>
        <p class="text-ink-muted">{{ performance/index }} view content.</p>
    </x-card>
</x-app-layout>
