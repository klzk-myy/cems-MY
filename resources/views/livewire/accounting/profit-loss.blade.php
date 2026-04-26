<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Profit & Loss</h1>
        <p class="text-sm text-gray-500">Revenue and expenses for the period</p>
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
            <span class="text-sm text-gray-500">{{ $fromDate }} - {{ $toDate }}</span>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h4 class="font-semibold mb-4 text-green-600">Revenue</h4>
                    @forelse($pl['revenues'] ?? [] as $item)
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>{{ $item['account_name'] }}</span>
                        <span class="font-mono">{{ number_format((float) $item['amount'], 2) }}</span>
                    </div>
                    @empty
                    <p class="text-gray-500 py-2">No revenue accounts</p>
                    @endforelse
                    <div class="flex justify-between py-3 font-semibold border-t-2 border-gray-900 mt-2">
                        <span>Total Revenue</span>
                        <span class="font-mono text-green-600">{{ number_format((float) ($pl['total_revenue'] ?? 0), 2) }}</span>
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold mb-4 text-red-600">Expenses</h4>
                    @forelse($pl['expenses'] ?? [] as $item)
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>{{ $item['account_name'] }}</span>
                        <span class="font-mono">{{ number_format((float) $item['amount'], 2) }}</span>
                    </div>
                    @empty
                    <p class="text-gray-500 py-2">No expense accounts</p>
                    @endforelse
                    <div class="flex justify-between py-3 font-semibold border-t-2 border-gray-900 mt-2">
                        <span>Total Expenses</span>
                        <span class="font-mono text-red-600">{{ number_format((float) ($pl['total_expenses'] ?? 0), 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Net Profit / Loss --}}
            <div class="mt-8 pt-4 border-t-2 border-gray-900">
                <div class="flex justify-between text-lg font-bold">
                    <span>Net Profit / (Loss)</span>
                    <span class="font-mono {{ ($pl['net_profit'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format((float) ($pl['net_profit'] ?? 0), 2) }} MYR
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>