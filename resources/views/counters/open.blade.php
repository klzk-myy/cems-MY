<x-app-layout title="{{ ucfirst(str_replace('-', ' ', open)) }} Counter">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', open)) }} Counter" description="Counter management" />
    <x-card>
        <p class="text-ink-muted">Counter {{ open }} view content.</p>
    </x-card>
</x-app-layout>
