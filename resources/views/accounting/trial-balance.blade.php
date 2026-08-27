<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], trial-balance)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], trial-balance)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ trial-balance }} view content.</p>
    </x-card>
</x-app-layout>
