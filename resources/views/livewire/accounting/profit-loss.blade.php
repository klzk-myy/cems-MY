<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[--color-ink]">Profit & Loss</h1>
        <p class="text-sm text-[--color-ink-muted]">Revenue and expenses for the period</p>
    </div>

    {{-- Date Range Filter --}}
    <div class="card mb-6">
        <div class="card-body flex items-center gap-4">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium">From:</label>
                <input type="date" wire:model.live="fromDate" class="input w-auto" />
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium">To:</label>
                <input type="date" wire:model.live="toDate" class="input w-auto" />
            </div>
        </div>
    </div>

    {{-- P&L Statement --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Profit & Loss Statement</h3>
            <span class="text-sm text-[--color-ink-muted]">{{ $fromDate }} - {{ $toDate }}</span>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h4 class="font-semibold mb-4 text-[--color-success]">Revenue</h4>
                    @forelse($pl['revenues'] ?? [] as $item)
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>{{ $item['account_name'] }}</span>
                        <span class="font-mono">{{ number_format((float) $item['amount'], 2) }}</span>
                    </div>
                    @empty
                    <p class="text-[--color-ink-muted] py-2">No revenue accounts</p>
                    @endforelse
                    <div class="flex justify-between py-3 font-semibold border-t-2 border-[--color-ink] mt-2">
                        <span>Total Revenue</span>
                        <span class="font-mono text-[--color-success]">{{ number_format((float) ($pl['total_revenue'] ?? 0), 2) }}</span>
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold mb-4 text-[--color-danger]">Expenses</h4>
                    @forelse($pl['expenses'] ?? [] as $item)
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>{{ $item['account_name'] }}</span>
                        <span class="font-mono">{{ number_format((float) $item['amount'], 2) }}</span>
                    </div>
                    @empty
                    <p class="text-[--color-ink-muted] py-2">No expense accounts</p>
                    @endforelse
                    <div class="flex justify-between py-3 font-semibold border-t-2 border-[--color-ink] mt-2">
                        <span>Total Expenses</span>
                        <span class="font-mono text-[--color-danger]">{{ number_format((float) ($pl['total_expenses'] ?? 0), 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Net Profit / Loss --}}
            <div class="mt-8 pt-4 border-t-2 border-[--color-ink]">
                <div class="flex justify-between text-lg font-bold">
                    <span>Net Profit / (Loss)</span>
                    <span class="font-mono {{ ($pl['net_profit'] ?? 0) >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                        {{ number_format((float) ($pl['net_profit'] ?? 0), 2) }} MYR
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>