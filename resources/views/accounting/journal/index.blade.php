<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], journal/index)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], journal/index)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ journal/index }} view content.</p>
    </x-card>
</x-app-layout>
