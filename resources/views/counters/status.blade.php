<x-app-layout title="{{ ucfirst(str_replace('-', ' ', status)) }} Counter">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', status)) }} Counter" description="Counter management" />
    <x-card>
        <p class="text-ink-muted">Counter {{ status }} view content.</p>
    </x-card>
</x-app-layout>
