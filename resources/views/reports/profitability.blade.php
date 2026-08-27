<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], profitability)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], profitability)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ profitability }} view content.</p>
    </x-card>
</x-app-layout>
