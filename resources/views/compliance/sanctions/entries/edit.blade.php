<x-app-layout title="{{ ucfirst(str_replace('/', ' ', sanctions/entries/edit)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', sanctions/entries/edit)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ sanctions/entries/edit }} view content.</p>
    </x-card>
</x-app-layout>
