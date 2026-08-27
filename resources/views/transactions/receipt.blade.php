<x-app-layout title="{{ ucfirst(str_replace('-', ' ', receipt)) }} Transaction">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', receipt)) }} Transaction" description="Transaction management" />
    <x-card>
        <p class="text-ink-muted">Transaction {{ receipt }} view content.</p>
    </x-card>
</x-app-layout>
