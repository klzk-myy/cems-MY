<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/ledger-account)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/ledger-account)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reports/ledger-account }} view content.</p>
    </x-card>
</x-app-layout>
