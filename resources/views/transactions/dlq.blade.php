<x-app-layout title="{{ ucfirst(str_replace('-', ' ', dlq)) }} Transaction">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', dlq)) }} Transaction" description="Transaction management" />
    <x-card>
        <p class="text-ink-muted">Transaction {{ dlq }} view content.</p>
    </x-card>
</x-app-layout>
