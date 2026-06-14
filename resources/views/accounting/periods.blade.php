<x-app-layout title="Accounting Periods">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Accounting Periods</h1>
                <p class="mt-1 text-sm text-ink-muted">Manage monthly accounting periods</p>
            </div>
            <div class="flex items-center gap-3">
                <select class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                    <option value="2026">FY 2026</option>
                    <option value="2025">FY 2025</option>
                </select>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    + New Period
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-surface border border-border rounded-xl p-4">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                    <option value="archived">Archived</option>
                </select>
                <input type="text" placeholder="Search periods..." class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg md:w-64">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">Filter</button>
            </div>
        </div>

        <!-- Periods Table -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-canvas-subtle border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Period</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Start Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">End Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Revenue</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Expenses</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P01</td>
                        <td class="px-4 py-3 text-sm">January 2026</td>
                        <td class="px-4 py-3 text-sm">2026-01-01</td>
                        <td class="px-4 py-3 text-sm">2026-01-31</td>
                        <td class="px-4 py-3 text-sm text-right">125,430.00</td>
                        <td class="px-4 py-3 text-sm text-right">98,210.00</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-canvas-subtle text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P02</td>
                        <td class="px-4 py-3 text-sm">February 2026</td>
                        <td class="px-4 py-3 text-sm">2026-02-01</td>
                        <td class="px-4 py-3 text-sm">2026-02-28</td>
                        <td class="px-4 py-3 text-sm text-right">132,870.00</td>
                        <td class="px-4 py-3 text-sm text-right">101,450.00</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-canvas-subtle text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P03</td>
                        <td class="px-4 py-3 text-sm">March 2026</td>
                        <td class="px-4 py-3 text-sm">2026-03-01</td>
                        <td class="px-4 py-3 text-sm">2026-03-31</td>
                        <td class="px-4 py-3 text-sm text-right">141,200.00</td>
                        <td class="px-4 py-3 text-sm text-right">108,900.00</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-canvas-subtle text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">April 2026</td>
                        <td class="px-4 py-3 text-sm">2026-04-01</td>
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-sm text-right">138,500.00</td>
                        <td class="px-4 py-3 text-sm text-right">105,750.00</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-canvas-subtle text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P05</td>
                        <td class="px-4 py-3 text-sm">May 2026</td>
                        <td class="px-4 py-3 text-sm">2026-05-01</td>
                        <td class="px-4 py-3 text-sm">2026-05-31</td>
                        <td class="px-4 py-3 text-sm text-right">95,420.00</td>
                        <td class="px-4 py-3 text-sm text-right">78,900.00</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P06</td>
                        <td class="px-4 py-3 text-sm">June 2026</td>
                        <td class="px-4 py-3 text-sm">2026-06-01</td>
                        <td class="px-4 py-3 text-sm">2026-06-30</td>
                        <td class="px-4 py-3 text-sm text-right">-</td>
                        <td class="px-4 py-3 text-sm text-right">-</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Future</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800" disabled>View</button>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-canvas-subtle border-t border-border">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-sm font-medium text-ink">Total YTD</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">633,420.00</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">493,210.00</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">Showing 1-6 of 6 periods</p>
            <div class="flex gap-2">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border disabled:opacity-50" disabled>Previous</button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border disabled:opacity-50" disabled>Next</button>
            </div>
        </div>
    </div>
</x-app-layout>
