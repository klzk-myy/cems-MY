<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], monthly-trends)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], monthly-trends)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ monthly-trends }} view content.</p>
    </x-card>
</x-app-layout>
