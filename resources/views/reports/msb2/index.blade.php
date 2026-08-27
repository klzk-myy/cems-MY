<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], msb2/index)) }} Report">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], msb2/index)) }} Report" description="Reports and analytics" />
    <x-card>
        <p class="text-ink-muted">Report {{ msb2/index }} view content.</p>
    </x-card>
</x-app-layout>
