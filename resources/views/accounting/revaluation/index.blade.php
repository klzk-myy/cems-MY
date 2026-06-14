<x-app-layout title="Currency Revaluation">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Currency Revaluation</h1>
                <p class="mt-1 text-sm text-ink-muted">Revalue currency positions based on exchange rates</p>
            </div>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                Run Revaluation
            </button>
        </div>

        <!-- Info Card -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="text-sm font-medium text-blue-800">Monthly Revaluation Required</p>
                    <p class="text-sm text-blue-600">Run revaluation to update currency position values based on current exchange rates.</p>
                </div>
            </div>
        </div>

        <!-- Current Rates -->
        <div class="bg-surface border border-border rounded-xl p-6">
            <h3 class="text-sm font-medium text-ink mb-4">Current Exchange Rates</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 border border-border rounded-lg">
                    <p class="text-sm text-ink-muted">USD/MYR</p>
                    <p class="mt-1 text-xl font-semibold text-ink">4.7200</p>
                    <p class="text-xs text-green-600">+0.0200 from base</p>
                </div>
                <div class="p-4 border border-border rounded-lg">
                    <p class="text-sm text-ink-muted">SGD/MYR</p>
                    <p class="mt-1 text-xl font-semibold text-ink">3.5100</p>
                    <p class="text-xs text-green-600">+0.0150 from base</p>
                </div>
                <div class="p-4 border border-border rounded-lg">
                    <p class="text-sm text-ink-muted">GBP/MYR</p>
                    <p class="mt-1 text-xl font-semibold text-ink">5.9500</p>
                    <p class="text-xs text-red-600">-0.0100 from base</p>
                </div>
                <div class="p-4 border border-border rounded-lg">
                    <p class="text-sm text-ink-muted">EUR/MYR</p>
                    <p class="mt-1 text-xl font-semibold text-ink">5.1800</p>
                    <p class="text-xs text-green-600">+0.0250 from base</p>
                </div>
            </div>
        </div>

        <!-- Revaluation Preview -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-border">
                <h3 class="text-sm font-medium text-ink">Revaluation Preview - May 2026</h3>
            </div>
            <table class="w-full">
                <thead class="bg-canvas-subtle border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Base Rate</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Current Rate</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Base Value (MYR)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Current Value (MYR)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Gain/Loss</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">USD</td>
                        <td class="px-4 py-3 text-sm text-right">50,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">4.7000</td>
                        <td class="px-4 py-3 text-sm text-right">4.7200</td>
                        <td class="px-4 py-3 text-sm text-right">235,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">236,000.00</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+1,000.00</td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">SGD</td>
                        <td class="px-4 py-3 text-sm text-right">25,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">3.4950</td>
                        <td class="px-4 py-3 text-sm text-right">3.5100</td>
                        <td class="px-4 py-3 text-sm text-right">87,375.00</td>
                        <td class="px-4 py-3 text-sm text-right">87,750.00</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+375.00</td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">GBP</td>
                        <td class="px-4 py-3 text-sm text-right">10,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">5.9600</td>
                        <td class="px-4 py-3 text-sm text-right">5.9500</td>
                        <td class="px-4 py-3 text-sm text-right">59,600.00</td>
                        <td class="px-4 py-3 text-sm text-right">59,500.00</td>
                        <td class="px-4 py-3 text-sm text-right text-red-600">-100.00</td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">EUR</td>
                        <td class="px-4 py-3 text-sm text-right">15,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">5.1550</td>
                        <td class="px-4 py-3 text-sm text-right">5.1800</td>
                        <td class="px-4 py-3 text-sm text-right">77,325.00</td>
                        <td class="px-4 py-3 text-sm text-right">77,700.00</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+375.00</td>
                    </tr>
                </tbody>
                <tfoot class="bg-canvas-subtle border-t border-border">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-sm font-medium text-ink">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">460,950.00</td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-green-600">+1,650.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Previous Revaluation -->
        <div class="bg-surface border border-border rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-ink">Last Revaluation</h3>
                    <p class="mt-1 text-sm text-ink-muted">April 30, 2026</p>
                </div>
                <a href="{{ route('accounting.revaluation.history') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">
                    View History
                </a>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">Cancel</button>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">Confirm Revaluation</button>
        </div>
    </div>
</x-app-layout>
