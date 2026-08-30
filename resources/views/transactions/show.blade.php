<x-app-layout title="Transaction Details">
    <x-page-header title="Transaction Details" description="View transaction information">
        <x-slot:actions>
            <a href="{{ route('transactions.index') }}"><x-button variant="secondary">Back</x-button></a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Transaction Information">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <x-detail-row label="Reference">TXN-001</x-detail-row>
                    <x-detail-row label="Status" value-class=""><x-badge variant="success">Completed</x-badge></x-detail-row>
                    <x-detail-row label="Customer">John Doe</x-detail-row>
                    <x-detail-row label="Amount"><x-money :amount="10000" currency="MYR" :decimals="0" /></x-detail-row>
                </dl>
            </x-card>
        </div>
        <div>
            <x-card title="Actions">
                <div class="space-y-2">
                    <x-button variant="secondary" class="w-full">Print Receipt</x-button>
                    <x-button variant="danger" class="w-full">Cancel</x-button>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
