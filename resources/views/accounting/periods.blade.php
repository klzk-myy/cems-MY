<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], periods)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], periods)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ periods }} view content.</p>
    </x-card>
</x-app-layout>
