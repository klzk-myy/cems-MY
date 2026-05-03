<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Financial Ratios</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex gap-4 mb-6">
                <select wire:model="period" class="rounded border border-[var(--color-border)] px-3 py-2">
                    @foreach($periods as $p)
                    <option value="{{ $p->start_date }}_{{ $p->end_date }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <button wire:click="calculate" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Calculate</button>
            </div>

            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Liquidity Ratios</h2>
            <div class="grid grid-cols-3 gap-6 mb-8">
                <div class="border border-[var(--color-border)] rounded p-4">
                    <p class="text-sm text-gray-500">Current Ratio</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($ratios['current_ratio'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Target: > 1.5</p>
                </div>
                <div class="border border-[var(--color-border)] rounded p-4">
                    <p class="text-sm text-gray-500">Quick Ratio</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($ratios['quick_ratio'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Target: > 1.0</p>
                </div>
                <div class="border border-[var(--color-border)] rounded p-4">
                    <p class="text-sm text-gray-500">Cash Ratio</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($ratios['cash_ratio'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Target: > 0.2</p>
                </div>
            </div>

            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Profitability Ratios</h2>
            <div class="grid grid-cols-3 gap-6 mb-8">
                <div class="border border-[var(--color-border)] rounded p-4">
                    <p class="text-sm text-gray-500">Net Profit Margin</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($ratios['net_profit_margin'], 1) }}%</p>
                </div>
                <div class="border border-[var(--color-border)] rounded p-4">
                    <p class="text-sm text-gray-500">ROA</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($ratios['roa'], 1) }}%</p>
                </div>
                <div class="border border-[var(--color-border)] rounded p-4">
                    <p class="text-sm text-gray-500">ROE</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($ratios['roe'], 1) }}%</p>
                </div>
            </div>

            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Leverage Ratios</h2>
            <div class="grid grid-cols-2 gap-6">
                <div class="border border-[var(--color-border)] rounded p-4">
                    <p class="text-sm text-gray-500">Debt to Equity</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($ratios['debt_to_equity'], 2) }}</p>
                </div>
                <div class="border border-[var(--color-border)] rounded p-4">
                    <p class="text-sm text-gray-500">Debt Ratio</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($ratios['debt_ratio'], 2) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>