<x-app-layout title="Financial Ratios">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Financial Ratios</h1>
                <p class="mt-1 text-sm text-ink-muted">Key financial performance indicators</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Profit Margin</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-400">-</p>
                <p class="mt-1 text-xs text-gray-400">Net profit / Total revenue</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Current Ratio</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-400">-</p>
                <p class="mt-1 text-xs text-gray-400">Current assets / Current liabilities</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Debt to Equity</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-400">-</p>
                <p class="mt-1 text-xs text-gray-400">Total liabilities / Total equity</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Return on Equity</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-400">-</p>
                <p class="mt-1 text-xs text-gray-400">Net profit / Total equity</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Asset Turnover</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-400">-</p>
                <p class="mt-1 text-xs text-gray-400">Total revenue / Total assets</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <h3 class="text-xs font-medium text-ink-muted uppercase">Expense Ratio</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-400">-</p>
                <p class="mt-1 text-xs text-gray-400">Total expenses / Total revenue</p>
            </div>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="text-sm text-amber-800">
                <strong>Note:</strong> Financial ratio computation is available via <code>FinancialRatioService</code>. 
                A period selector and automated calculation will be added in a future update.
            </p>
        </div>
    </div>
</x-app-layout>
