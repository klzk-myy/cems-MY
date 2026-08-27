<x-app-layout title="{{ ucfirst(str_replace('/', ' ', screening/history)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', screening/history)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ screening/history }} view content.</p>
    </x-card>
</x-app-layout>
