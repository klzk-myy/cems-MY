<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], schedules/edit)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], schedules/edit)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ schedules/edit }} view content.</p>
    </x-card>
</x-app-layout>
