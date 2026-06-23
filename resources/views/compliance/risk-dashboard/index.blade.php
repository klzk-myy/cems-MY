<x-app-layout title="Risk Dashboard">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Risk Dashboard"
            description="Customer risk overview and analytics"
        />

        <x-stat-grid cols="4">
            <x-stat-card label="High Risk" value="12" color="red" />
            <x-stat-card label="Medium Risk" value="28" color="yellow" />
            <x-stat-card label="Low Risk" value="156" color="green" />
            <x-stat-card label="PEP Customers" value="8" color="purple" />
        </x-stat-grid>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Risk Distribution">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">High Risk</span>
                        <div class="flex items-center gap-3">
                            <x-progress-bar :value="6" :max="100" color="bg-danger" width="w-48" />
                            <span class="text-sm font-medium text-ink">6%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Medium Risk</span>
                        <div class="flex items-center gap-3">
                            <x-progress-bar :value="14" :max="100" color="bg-warning" width="w-48" />
                            <span class="text-sm font-medium text-ink">14%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Low Risk</span>
                        <div class="flex items-center gap-3">
                            <x-progress-bar :value="80" :max="100" color="bg-success" width="w-48" />
                            <span class="text-sm font-medium text-ink">80%</span>
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card title="High Risk Customers">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-ink">Ahmad Razali</p>
                            <p class="text-xs text-ink-muted">CUST-001</p>
                        </div>
                        <x-button variant="ghost" size="sm" href="#">View</x-button>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-ink">Siti Nurhaliza</p>
                            <p class="text-xs text-ink-muted">CUST-042</p>
                        </div>
                        <x-button variant="ghost" size="sm" href="#">View</x-button>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-ink">Tan Wei Ming</p>
                            <p class="text-xs text-ink-muted">CUST-108</p>
                        </div>
                        <x-button variant="ghost" size="sm" href="#">View</x-button>
                    </div>
                </div>
            </x-card>
        </div>

        <x-card title="Recent Risk Score Changes">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Previous Score</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">New Score</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Change Reason</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">Ahmad Razali</td>
                        <td class="px-4 py-3 text-sm"><x-badge variant="warning">Medium</x-badge></td>
                        <td class="px-4 py-3 text-sm"><x-badge variant="danger">High</x-badge></td>
                        <td class="px-4 py-3 text-sm text-ink-muted">Velocity alert triggered</td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-15</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">Lee Mei Ling</td>
                        <td class="px-4 py-3 text-sm"><x-badge variant="success">Low</x-badge></td>
                        <td class="px-4 py-3 text-sm"><x-badge variant="warning">Medium</x-badge></td>
                        <td class="px-4 py-3 text-sm text-ink-muted">Transaction pattern change</td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-14</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
