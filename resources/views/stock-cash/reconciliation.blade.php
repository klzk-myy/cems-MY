<x-app-layout title="{{ ucfirst(str_replace('-', ' ', reconciliation)) }} Stock Cash">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', reconciliation)) }} Stock Cash" description="Cash inventory management" />
    <x-card>
        <p class="text-ink-muted">Stock cash {{ reconciliation }} view content.</p>
    </x-card>
</x-app-layout>
