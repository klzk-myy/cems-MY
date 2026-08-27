<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], schedules/index)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], schedules/index)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ schedules/index }} view content.</p>
    </x-card>
</x-app-layout>
