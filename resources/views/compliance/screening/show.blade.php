<x-app-layout title="{{ ucfirst(str_replace('/', ' ', screening/show)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', screening/show)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ screening/show }} view content.</p>
    </x-card>
</x-app-layout>
