<x-app-layout title="{{ ucfirst(str_replace('/', ' ', notifications/preferences)) }}">
    <x-page-header title="{{ ucfirst(str_replace('/', ' ', notifications/preferences)) }}" description="Management" />
    <x-card>
        <p class="text-ink-muted">{{ notifications/preferences }} view content.</p>
    </x-card>
</x-app-layout>
