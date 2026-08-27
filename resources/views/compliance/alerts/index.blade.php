<x-app-layout title="{{ ucfirst(str_replace('/', ' ', alerts/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', alerts/index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ alerts/index }} view content.</p>
    </x-card>
</x-app-layout>
