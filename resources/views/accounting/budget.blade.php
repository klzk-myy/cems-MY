<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], budget)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], budget)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ budget }} view content.</p>
    </x-card>
</x-app-layout>
