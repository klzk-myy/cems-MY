<div>
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[--color-ink]">Position - {{ $positionData['currency_code'] ?? 'N/A' }}</h1>
            <p class="text-sm text-[--color-ink-muted]">Currency position details</p>
        </div>
        <a href="{{ route('stock-cash.index') }}" class="btn btn-secondary">Back</a>
    </div>

    {{-- Position Details --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 bg-[--color-surface-elevated] rounded">
            <dt class="text-sm text-[--color-ink-muted]">Quantity</dt>
            <dd class="text-2xl font-mono">{{ number_format((float) ($positionData['quantity'] ?? 0), 2) }}</dd>
        </div>
        <div class="p-4 bg-[--color-surface-elevated] rounded">
            <dt class="text-sm text-[--color-ink-muted]">Average Cost</dt>
            <dd class="text-2xl font-mono">RM {{ number_format((float) ($positionData['avg_cost'] ?? 0), 4) }}</dd>
        </div>
        <div class="p-4 bg-[--color-surface-elevated] rounded">
            <dt class="text-sm text-[--color-ink-muted]">Market Value</dt>
            <dd class="text-2xl font-mono">RM {{ number_format((float) ($positionData['market_value'] ?? 0), 2) }}</dd>
        </div>
        <div class="p-4 bg-[--color-surface-elevated] rounded">
            <dt class="text-sm text-[--color-ink-muted]">Unrealized P/L</dt>
            @php
                $pl = (float) ($positionData['unrealized_pl'] ?? 0);
                $plClass = $pl >= 0 ? 'text-green-600' : 'text-red-600';
            @endphp
            <dd class="text-2xl font-mono {{ $plClass }}">
                {{ $pl >= 0 ? '+' : '' }}RM {{ number_format($pl, 2) }}
            </dd>
        </div>
    </div>

    {{-- Additional Details --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
        <div class="p-4 bg-[--color-surface-elevated] rounded">
            <dt class="text-sm text-[--color-ink-muted]">Last Valuation Rate</dt>
            <dd class="text-xl font-mono">{{ number_format((float) ($positionData['last_valuation_rate'] ?? 0), 4) }}</dd>
        </div>
        <div class="p-4 bg-[--color-surface-elevated] rounded">
            <dt class="text-sm text-[--color-ink-muted]">Last Valuation At</dt>
            <dd class="text-xl font-mono">{{ $positionData['last_valuation_at'] ?? 'N/A' }}</dd>
        </div>
        <div class="p-4 bg-[--color-surface-elevated] rounded">
            <dt class="text-sm text-[--color-ink-muted]">Till ID</dt>
            <dd class="text-xl font-mono">{{ $positionData['till_id'] ?? 'N/A' }}</dd>
        </div>
    </div>

    {{-- Recent Transactions --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Transactions</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">MYR Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                    <tr>
                        <td class="font-mono">{{ $tx['created_at'] }}</td>
                        <td>
                            <span class="badge {{ $tx['type'] === 'Buy' ? 'badge-success' : 'badge-warning' }}">
                                {{ $tx['type'] }}
                            </span>
                        </td>
                        <td class="font-mono text-right">{{ number_format((float) $tx['amount'], 2) }}</td>
                        <td class="font-mono text-right">{{ number_format((float) $tx['rate'], 4) }}</td>
                        <td class="font-mono text-right">RM {{ number_format((float) $tx['myr_value'], 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No transactions</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
