<x-app-layout title="Dashboard">
    <x-page-header title="Dashboard" description="Overview of your exchange operations">
        <x-slot:actions>
            <x-button variant="secondary">Export</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-stat-grid>
        <x-stat-card label="Total Transactions" value="1,234" color="blue" :trend="12" />
        <x-stat-card label="Active Customers" value="567" color="green" :trend="8" />
        <x-stat-card label="Revenue" value="RM 45,678" color="purple" :trend="23" />
        <x-stat-card label="Alerts" value="12" color="red" :trend="-5" />
    </x-stat-grid>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card title="Recent Transactions">
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink-muted" colspan="4">No transactions yet</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Exchange Rates">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-ink">USD/MYR</span>
                    <span class="text-sm font-medium text-ink">4.20</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-ink">EUR/MYR</span>
                    <span class="text-sm font-medium text-ink">4.65</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-ink">SGD/MYR</span>
                    <span class="text-sm font-medium text-ink">3.10</span>
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
