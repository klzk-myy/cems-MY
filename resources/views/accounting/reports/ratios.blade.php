<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/ratios)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/ratios)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reports/ratios }} view content.</p>
    </x-card>
</x-app-layout>
