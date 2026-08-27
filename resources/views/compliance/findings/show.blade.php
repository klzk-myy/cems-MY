<x-app-layout title="{{ ucfirst(str_replace('/', ' ', findings/show)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', findings/show)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ findings/show }} view content.</p>
    </x-card>
</x-app-layout>
