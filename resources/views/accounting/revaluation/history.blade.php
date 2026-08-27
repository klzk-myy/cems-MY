<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], revaluation/history)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], revaluation/history)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ revaluation/history }} view content.</p>
    </x-card>
</x-app-layout>
