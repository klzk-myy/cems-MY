<x-app-layout title="{{ ucfirst(str_replace('/', ' ', sanctions/entries/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', sanctions/entries/index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ sanctions/entries/index }} view content.</p>
    </x-card>
</x-app-layout>
