<x-app-layout title="{{ ucfirst(str_replace('-', ' ', handover)) }} Counter">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', handover)) }} Counter" description="Counter management" />
    <x-card>
        <p class="text-ink-muted">Counter {{ handover }} view content.</p>
    </x-card>
</x-app-layout>
