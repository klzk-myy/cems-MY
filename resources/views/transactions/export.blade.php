<x-app-layout title="{{ ucfirst(str_replace('-', ' ', export)) }} Transaction">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', export)) }} Transaction" description="Transaction management" />
    <x-card>
        <p class="text-ink-muted">Transaction {{ export }} view content.</p>
    </x-card>
</x-app-layout>
