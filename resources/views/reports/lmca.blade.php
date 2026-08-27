<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], lmca)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], lmca)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ lmca }} view content.</p>
    </x-card>
</x-app-layout>
