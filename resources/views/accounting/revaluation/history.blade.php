<x-app-layout title="Revaluation History">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Revaluation History</h1>
                <p class="mt-1 text-sm text-ink-muted">View historical currency revaluation records</p>
            </div>
            <a href="{{ route('accounting.revaluation') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">
                Back to Revaluation
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-surface border border-border rounded-xl p-4">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                    <option value="">All Fiscal Years</option>
                    <option value="2026">FY 2026</option>
                    <option value="2025">FY 2025</option>
                </select>
                <select class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                    <option value="">All Currencies</option>
                    <option value="USD">USD</option>
                    <option value="SGD">SGD</option>
                    <option value="GBP">GBP</option>
                    <option value="EUR">EUR</option>
                </select>
                <input type="text" placeholder="Search..." class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg md:w-64">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">Filter</button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-surface border border-border rounded-xl p-4">
                <p class="text-sm text-ink-muted">Total Revaluations</p>
                <p class="mt-1 text-2xl font-semibold text-ink">12</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <p class="text-sm text-ink-muted">Total Gains YTD</p>
                <p class="mt-1 text-2xl font-semibold text-green-600">RM 28,450.00</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <p class="text-sm text-ink-muted">Total Losses YTD</p>
                <p class="mt-1 text-2xl font-semibold text-red-600">RM 12,350.00</p>
            </div>
        </div>

        <!-- Revaluation History Table -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-canvas-subtle border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Period</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Base Rate</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">End Rate</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Gain/Loss</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">USD</td>
                        <td class="px-4 py-3 text-sm text-right">50,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">4.6800</td>
                        <td class="px-4 py-3 text-sm text-right">4.7000</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+1,000.00</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">SGD</td>
                        <td class="px-4 py-3 text-sm text-right">25,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">3.4800</td>
                        <td class="px-4 py-3 text-sm text-right">3.4950</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+375.00</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">GBP</td>
                        <td class="px-4 py-3 text-sm text-right">10,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">5.9700</td>
                        <td class="px-4 py-3 text-sm text-right">5.9600</td>
                        <td class="px-4 py-3 text-sm text-right text-red-600">-100.00</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-03-31</td>
                        <td class="px-4 py-3 text-sm">P03</td>
                        <td class="px-4 py-3 text-sm">USD</td>
                        <td class="px-4 py-3 text-sm text-right">45,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">4.6500</td>
                        <td class="px-4 py-3 text-sm text-right">4.6800</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+1,350.00</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-03-31</td>
                        <td class="px-4 py-3 text-sm">P03</td>
                        <td class="px-4 py-3 text-sm">EUR</td>
                        <td class="px-4 py-3 text-sm text-right">20,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">5.1200</td>
                        <td class="px-4 py-3 text-sm text-right">5.1550</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+700.00</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-02-28</td>
                        <td class="px-4 py-3 text-sm">P02</td>
                        <td class="px-4 py-3 text-sm">SGD</td>
                        <td class="px-4 py-3 text-sm text-right">30,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">3.5100</td>
                        <td class="px-4 py-3 text-sm text-right">3.4800</td>
                        <td class="px-4 py-3 text-sm text-right text-red-600">-900.00</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-01-31</td>
                        <td class="px-4 py-3 text-sm">P01</td>
                        <td class="px-4 py-3 text-sm">USD</td>
                        <td class="px-4 py-3 text-sm text-right">40,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">4.6200</td>
                        <td class="px-4 py-3 text-sm text-right">4.6500</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+1,200.00</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">Showing 1-7 of 12 records</p>
            <div class="flex gap-2">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border disabled:opacity-50" disabled>Previous</button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">Next</button>
            </div>
        </div>
    </div>
</x-app-layout>
