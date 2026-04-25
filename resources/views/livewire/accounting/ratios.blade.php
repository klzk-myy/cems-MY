<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[--color-ink]">Financial Ratios</h1>
        <p class="text-sm text-[--color-ink-muted]">Liquidity, profitability, leverage, and efficiency ratios</p>
    </div>

    {{-- Date Filter --}}
    <div class="card mb-6">
        <div class="card-body flex items-center gap-4">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium">Period:</label>
                <input type="date" wire:model.live="fromDate" class="input w-auto" />
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium">to</label>
                <input type="date" wire:model.live="toDate" class="input w-auto" />
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium">As of:</label>
                <input type="date" wire:model.live="asOfDate" class="input w-auto" />
            </div>
        </div>
    </div>

    @if($hasData && !empty($ratios))
    {{-- Ratios Display --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Liquidity Ratios --}}
        @if(isset($ratios['liquidity']))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Liquidity Ratios</h3>
            </div>
            <div class="card-body">
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Current Ratio</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['liquidity']['current_ratio'] ?? 0), 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Quick Ratio</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['liquidity']['quick_ratio'] ?? 0), 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Cash Ratio</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['liquidity']['cash_ratio'] ?? 0), 2) }}</span>
                    </div>
                    <div class="mt-4 pt-2 text-sm text-[--color-ink-muted]">
                        <p>Current Assets: {{ number_format((float) ($ratios['liquidity']['current_assets'] ?? 0), 2) }} MYR</p>
                        <p>Current Liabilities: {{ number_format((float) ($ratios['liquidity']['current_liabilities'] ?? 0), 2) }} MYR</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Profitability Ratios --}}
        @if(isset($ratios['profitability']))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Profitability Ratios</h3>
            </div>
            <div class="card-body">
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Gross Profit Margin</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['profitability']['gross_profit_margin'] ?? 0) * 100, 2) }}%</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Net Profit Margin</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['profitability']['net_profit_margin'] ?? 0) * 100, 2) }}%</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Return on Equity (ROE)</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['profitability']['roe'] ?? 0), 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Return on Assets (ROA)</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['profitability']['roa'] ?? 0), 2) }}</span>
                    </div>
                    <div class="mt-4 pt-2 text-sm text-[--color-ink-muted]">
                        <p>Revenue: {{ number_format((float) ($ratios['profitability']['revenue'] ?? 0), 2) }} MYR</p>
                        <p>Net Income: {{ number_format((float) ($ratios['profitability']['net_income'] ?? 0), 2) }} MYR</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Leverage Ratios --}}
        @if(isset($ratios['leverage']))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Leverage Ratios</h3>
            </div>
            <div class="card-body">
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Debt to Equity</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['leverage']['debt_to_equity'] ?? 0), 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Debt to Assets</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['leverage']['debt_to_assets'] ?? 0), 2) }}</span>
                    </div>
                    <div class="mt-4 pt-2 text-sm text-[--color-ink-muted]">
                        <p>Total Debt: {{ number_format((float) ($ratios['leverage']['total_debt'] ?? 0), 2) }} MYR</p>
                        <p>Total Equity: {{ number_format((float) ($ratios['leverage']['equity'] ?? 0), 2) }} MYR</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Efficiency Ratios --}}
        @if(isset($ratios['efficiency']))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Efficiency Ratios</h3>
            </div>
            <div class="card-body">
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Asset Turnover</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['efficiency']['asset_turnover'] ?? 0), 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>Inventory Turnover</span>
                        <span class="font-mono font-medium">{{ number_format((float) ($ratios['efficiency']['inventory_turnover'] ?? 0), 2) }}</span>
                    </div>
                    <div class="mt-4 pt-2 text-sm text-[--color-ink-muted]">
                        <p>Revenue: {{ number_format((float) ($ratios['efficiency']['revenue'] ?? 0), 2) }} MYR</p>
                        <p>Total Assets: {{ number_format((float) ($ratios['efficiency']['total_assets'] ?? 0), 2) }} MYR</p>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
    @else
    {{-- Empty State --}}
    <div class="card">
        <div class="empty-state py-12">
            <div class="empty-state-icon">
                <svg class="w-12 h-12 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <p class="empty-state-title">No ratio data</p>
            <p class="empty-state-description">Select a date range to calculate financial ratios</p>
        </div>
    </div>
    @endif
</div>