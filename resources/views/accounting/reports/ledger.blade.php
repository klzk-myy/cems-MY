<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/ledger)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/ledger)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reports/ledger }} view content.</p>
    </x-card>
</x-app-layout>
