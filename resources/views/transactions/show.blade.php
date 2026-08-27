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
                    <div>
                        <dt class="text-ink-muted">Reference</dt>
                        <dd class="font-medium text-ink">TXN-001</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Status</dt>
                        <dd><x-badge variant="success">Completed</x-badge></dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Customer</dt>
                        <dd class="font-medium text-ink">John Doe</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Amount</dt>
                        <dd class="font-medium text-ink">RM 10,000</dd>
                    </div>
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
