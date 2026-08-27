<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], index)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], index)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ index }} view content.</p>
    </x-card>
</x-app-layout>
