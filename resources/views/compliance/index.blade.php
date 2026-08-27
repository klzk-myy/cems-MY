<x-app-layout title="{{ ucfirst(str_replace('/', ' ', index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ index }} view content.</p>
    </x-card>
</x-app-layout>
