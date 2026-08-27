<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], journal/show)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], journal/show)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ journal/show }} view content.</p>
    </x-card>
</x-app-layout>
