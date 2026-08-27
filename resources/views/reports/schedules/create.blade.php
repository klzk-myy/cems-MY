<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], schedules/create)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], schedules/create)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ schedules/create }} view content.</p>
    </x-card>
</x-app-layout>
