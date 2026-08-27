<x-app-layout title="{{ ucfirst(str_replace('/', ' ', str/show)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', str/show)) }}" description="Compliance management" />
    <x-card>
        <p class="text-ink-muted">Compliance {{ str/show }} view content.</p>
    </x-card>
</x-app-layout>
