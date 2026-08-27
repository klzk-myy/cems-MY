<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], compliance-summary)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], compliance-summary)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ compliance-summary }} view content.</p>
    </x-card>
</x-app-layout>
