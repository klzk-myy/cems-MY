<x-app-layout title="{{ ucfirst(str_replace('-', ' ', position)) }} Stock Cash">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', position)) }} Stock Cash" description="Cash inventory management" />
    <x-card>
        <p class="text-ink-muted">Stock cash {{ position }} view content.</p>
    </x-card>
</x-app-layout>
