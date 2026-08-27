<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reconciliation)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reconciliation)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reconciliation }} view content.</p>
    </x-card>
</x-app-layout>
