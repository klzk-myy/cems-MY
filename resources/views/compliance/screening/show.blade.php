<x-app-layout title="Screening Result">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header title="Screening Result" :actions="true">
            Transaction ID: TXN-2024-001

            <x-slot:actions>
                <x-button variant="secondary" href="#">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Transaction Details">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Transaction ID</label>
                    <p class="text-sm text-ink">TXN-2024-001</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Date</label>
                    <p class="text-sm text-ink">2024-01-15 10:30:00</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Type</label>
                    <p class="text-sm text-ink">Buy USD</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Amount</label>
                    <p class="text-sm text-ink">RM 28,000</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Customer</label>
                    <p class="text-sm text-ink">Ahmad Razali</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Counter</label>
                    <p class="text-sm text-ink">Counter 1 - KL Main</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Teller</label>
                    <p class="text-sm text-ink">Mike Tan</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Screening Status</label>
                    <p class="text-sm text-ink">
                        <x-badge variant="warning">Pending Review</x-badge>
                    </p>
                </div>
            </div>
        </x-card>

        <x-card title="Sanctions Screening">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-card-section title="Sanctions Check" :actions="true">
                    <x-slot:actions>
                        <x-badge variant="success">Clear</x-badge>
                    </x-slot:actions>

                    <div class="p-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">OFAC SDN</span>
                            <span class="text-success-text">Clear</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">UN Security Council</span>
                            <span class="text-success-text">Clear</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">EU Sanctions</span>
                            <span class="text-success-text">Clear</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">BNM List</span>
                            <span class="text-success-text">Clear</span>
                        </div>
                    </div>
                </x-card-section>

                <x-card-section title="AML Screening" :actions="true">
                    <x-slot:actions>
                        <x-badge variant="warning">Review</x-badge>
                    </x-slot:actions>

                    <div class="p-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Velocity Check</span>
                            <span class="text-warning-text">Flagged</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Structuring Check</span>
                            <span class="text-warning-text">Flagged</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">PEP Check</span>
                            <span class="text-success-text">Clear</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Adverse Media</span>
                            <span class="text-success-text">Clear</span>
                        </div>
                    </div>
                </x-card-section>
            </div>
        </x-card>

        <x-card title="Risk Indicators">
            <div class="space-y-4">
                <x-alert type="warning" title="High Transaction Velocity" :icon="true" class="mb-0">
                    Customer has conducted 5 transactions totaling RM 45,000 in the last 7 days
                </x-alert>

                <x-alert type="warning" title="Approaching STR Threshold" :icon="true" class="mb-0">
                    Transaction plus recent activity approaches RM 50,000 STR threshold
                </x-alert>
            </div>
        </x-card>

        <x-card title="Actions">
            <div class="flex flex-wrap gap-3">
                <x-button variant="primary">Approve Transaction</x-button>
                <x-button variant="secondary">Hold for Review</x-button>
                <x-button variant="secondary">Create Alert</x-button>
                <x-button variant="secondary">View Customer Profile</x-button>
                <x-button variant="danger">Reject Transaction</x-button>
            </div>
        </x-card>
    </div>
</x-app-layout>
