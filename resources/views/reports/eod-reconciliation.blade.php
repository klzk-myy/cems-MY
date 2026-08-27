<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], eod-reconciliation)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], eod-reconciliation)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ eod-reconciliation }} view content.</p>
    </x-card>
</x-app-layout>
