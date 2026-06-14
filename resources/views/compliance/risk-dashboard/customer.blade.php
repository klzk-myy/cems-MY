<x-app-layout title="Customer Risk Dashboard">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Customer Risk Dashboard"
            description="Risk assessment for: Ahmad Razali"
        />

        <x-card>
            <x-card-section>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Customer ID</label>
                        <p class="text-sm text-ink">CUST-001</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Risk Level</label>
                        <x-badge variant="danger">High</x-badge>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">CDD Level</label>
                        <p class="text-sm text-ink">Enhanced</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Account Status</label>
                        <x-badge variant="warning">Under Review</x-badge>
                    </div>
                </div>
            </x-card-section>
        </x-card>

        <x-card title="Risk Factors">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <x-card>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-ink-muted">Transaction Velocity</span>
                        <span class="text-sm font-medium text-danger-text">High Risk</span>
                    </div>
                    <x-progress-bar value="85" variant="danger" />
                    <p class="text-xs text-ink-muted mt-2">RM 125,000 in last 30 days</p>
                </x-card>
                <x-card>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-ink-muted">Structuring Risk</span>
                        <span class="text-sm font-medium text-danger-text">High Risk</span>
                    </div>
                    <x-progress-bar value="72" variant="danger" />
                    <p class="text-xs text-ink-muted mt-2">5 transactions near threshold</p>
                </x-card>
                <x-card>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-ink-muted">PEP Exposure</span>
                        <span class="text-sm font-medium text-warning-text">Medium</span>
                    </div>
                    <x-progress-bar value="50" variant="warning" />
                    <p class="text-xs text-ink-muted mt-2">Indirect connection detected</p>
                </x-card>
            </div>
        </x-card>

        <x-card title="Recent Transactions">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Risk Flag</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-15</td>
                        <td class="px-4 py-3 text-sm text-ink">Buy USD</td>
                        <td class="px-4 py-3 text-sm text-ink">RM 28,000</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="danger">Alert</x-badge>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-12</td>
                        <td class="px-4 py-3 text-sm text-ink">Sell USD</td>
                        <td class="px-4 py-3 text-sm text-ink">RM 35,000</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="danger">Alert</x-badge>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-10</td>
                        <td class="px-4 py-3 text-sm text-ink">Buy EUR</td>
                        <td class="px-4 py-3 text-sm text-ink">RM 25,000</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="warning">Review</x-badge>
                        </td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Actions">
            <div class="flex flex-wrap gap-3">
                <x-button variant="primary">Request EDD</x-button>
                <x-button variant="secondary">Lock Account</x-button>
                <x-button variant="secondary">View Full Profile</x-button>
                <x-button variant="secondary">Export Report</x-button>
            </div>
        </x-card>
    </div>
</x-app-layout>
