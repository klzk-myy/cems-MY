<x-app-layout title="{{ ucfirst(str_replace('-', ' ', cancel)) }} Transaction">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', cancel)) }} Transaction" description="Transaction management" />
    <x-card>
        <p class="text-ink-muted">Transaction {{ cancel }} view content.</p>
    </x-card>
</x-app-layout>
