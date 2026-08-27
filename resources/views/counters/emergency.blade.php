<x-app-layout title="{{ ucfirst(str_replace('-', ' ', emergency)) }} Counter">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', emergency)) }} Counter" description="Counter management" />
    <x-card>
        <p class="text-ink-muted">Counter {{ emergency }} view content.</p>
    </x-card>
</x-app-layout>
