<x-app-layout title="{{ ucfirst(str_replace('-', ' ', history)) }} Counter">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', history)) }} Counter" description="Counter management" />
    <x-card>
        <p class="text-ink-muted">Counter {{ history }} view content.</p>
    </x-card>
</x-app-layout>
