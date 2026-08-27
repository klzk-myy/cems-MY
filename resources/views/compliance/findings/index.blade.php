<x-app-layout title="{{ ucfirst(str_replace('/', ' ', findings/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', findings/index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ findings/index }} view content.</p>
    </x-card>
</x-app-layout>
