<x-app-layout title="{{ ucfirst(str_replace('-', ' ', close)) }} Counter">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', close)) }} Counter" description="Counter management" />
    <x-card>
        <p class="text-ink-muted">Counter {{ close }} view content.</p>
    </x-card>
</x-app-layout>
