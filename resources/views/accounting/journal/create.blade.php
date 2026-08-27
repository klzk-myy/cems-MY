<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], journal/create)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], journal/create)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ journal/create }} view content.</p>
    </x-card>
</x-app-layout>
