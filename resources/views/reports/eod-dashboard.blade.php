<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], eod-dashboard)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], eod-dashboard)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ eod-dashboard }} view content.</p>
    </x-card>
</x-app-layout>
