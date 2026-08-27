<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/profit-loss)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/profit-loss)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reports/profit-loss }} view content.</p>
    </x-card>
</x-app-layout>
