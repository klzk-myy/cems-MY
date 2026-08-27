<x-app-layout title="{{ ucfirst(str_replace('/', ' ', cases/show)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', cases/show)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ cases/show }} view content.</p>
    </x-card>
</x-app-layout>
