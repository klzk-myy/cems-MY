<x-app-layout title="{{ ucfirst(str_replace('/', ' ', screening/status)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', screening/status)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ screening/status }} view content.</p>
    </x-card>
</x-app-layout>
