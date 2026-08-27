<x-app-layout title="{{ ucfirst(str_replace('-', ' ', index)) }} Stock Cash">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', index)) }} Stock Cash" description="Cash inventory management" />
    <x-card>
        <p class="text-ink-muted">Stock cash {{ index }} view content.</p>
    </x-card>
</x-app-layout>
