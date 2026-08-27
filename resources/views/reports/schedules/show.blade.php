<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], schedules/show)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], schedules/show)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ schedules/show }} view content.</p>
    </x-card>
</x-app-layout>
