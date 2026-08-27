<x-app-layout title="{{ ucfirst(str_replace('/', ' ', sanctions/index)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', sanctions/index)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ sanctions/index }} view content.</p>
    </x-card>
</x-app-layout>
