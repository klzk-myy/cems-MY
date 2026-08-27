<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/balance-sheet)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/balance-sheet)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reports/balance-sheet }} view content.</p>
    </x-card>
</x-app-layout>
