<x-app-layout title="{{ ucfirst(str_replace('/', ' ', unified/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', unified/index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ unified/index }} view content.</p>
    </x-card>
</x-app-layout>
