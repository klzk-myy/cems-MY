<x-app-layout title="{{ ucfirst(str_replace('/', ' ', screening/matches/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', screening/matches/index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ screening/matches/index }} view content.</p>
    </x-card>
</x-app-layout>
