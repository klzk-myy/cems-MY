<x-app-layout title="{{ ucfirst(str_replace('/', ' ', sanctions/show)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', sanctions/show)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ sanctions/show }} view content.</p>
    </x-card>
</x-app-layout>
