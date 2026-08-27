<x-app-layout title="{{ ucfirst(str_replace('/', ' ', cases/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', cases/index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ cases/index }} view content.</p>
    </x-card>
</x-app-layout>
