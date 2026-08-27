<x-app-layout title="{{ ucfirst(str_replace('/', ' ', setup/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', setup/index)) }}" description="Management" />
    <x-card>
        <p class="text-ink-muted">{{ setup/index }} view content.</p>
    </x-card>
</x-app-layout>
