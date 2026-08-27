<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], chart-of-accounts/index)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], chart-of-accounts/index)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ chart-of-accounts/index }} view content.</p>
    </x-card>
</x-app-layout>
