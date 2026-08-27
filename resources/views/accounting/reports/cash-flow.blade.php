<x-app-layout title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/cash-flow)) }} Accounting">
    <x-page-header title="{{ ucfirst(str_replace(['/', '-'], [' ', ' '], reports/cash-flow)) }} Accounting" description="Financial management" />
    <x-card>
        <p class="text-ink-muted">Accounting {{ reports/cash-flow }} view content.</p>
    </x-card>
</x-app-layout>
