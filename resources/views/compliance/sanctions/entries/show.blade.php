<x-app-layout title="{{ ucfirst(str_replace('/', ' ', sanctions/entries/show)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', sanctions/entries/show)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ sanctions/entries/show }} view content.</p>
    </x-card>
</x-app-layout>
