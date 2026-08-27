<x-app-layout title="{{ ucfirst(str_replace('/', ' ', screening/matches/show)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', screening/matches/show)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ screening/matches/show }} view content.</p>
    </x-card>
</x-app-layout>
