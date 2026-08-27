<x-app-layout title="{{ ucfirst(str_replace('/', ' ', pep/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', pep/index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ pep/index }} view content.</p>
    </x-card>
</x-app-layout>
