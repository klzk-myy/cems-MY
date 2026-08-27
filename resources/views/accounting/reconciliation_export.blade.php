<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reconciliation_export)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reconciliation_export)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reconciliation_export }} view content.</p>
    </x-card>
</x-app-layout>
