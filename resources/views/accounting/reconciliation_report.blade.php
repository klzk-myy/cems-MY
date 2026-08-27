<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reconciliation_report)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reconciliation_report)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reconciliation_report }} view content.</p>
    </x-card>
</x-app-layout>
