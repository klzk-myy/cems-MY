<x-app-layout title="{{ ucfirst(str_replace('/', ' ', alerts/show)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', alerts/show)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ alerts/show }} view content.</p>
    </x-card>
</x-app-layout>
