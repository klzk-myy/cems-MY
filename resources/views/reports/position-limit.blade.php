<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], position-limit)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], position-limit)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ position-limit }} view content.</p>
    </x-card>
</x-app-layout>
