<x-app-layout title="{{ ucfirst(str_replace('-', ' ', confirm)) }} Transaction">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', confirm)) }} Transaction" description="Transaction management" />
    <x-card>
        <p class="text-ink-muted">Transaction {{ confirm }} view content.</p>
    </x-card>
</x-app-layout>
