<x-app-layout title="MSB2 Daily Transaction Summary">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">MSB2 Daily Transaction Summary</h1>
                <p class="text-sm text-ink-muted mt-1">Daily Summary of Money Service Business Transactions</p>
            </div>
            @if($isToday)
            <span class="inline-flex px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">Today</span>
            @endif
        </div>

        {{-- Date Selector --}}
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.msb2') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="date" class="block text-sm font-medium text-ink-muted mb-2">Select Date</label>
                    <input type="date" id="date" name="date" value="{{ $date }}" class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    View Report
                </button>
            </form>
            @if($nextBusinessDay)
            <p class="text-sm text-ink-muted mt-4 pt-4 border-t border-border">
                Next Business Day: <span class="font-medium text-ink">{{ \Carbon\Carbon::parse($nextBusinessDay)->format('d M Y (l)') }}</span>
            </p>
            @endif
        </div>

        {{-- Report Content --}}
        @if($reportGenerated)
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Total Transactions</p>
                <p class="text-2xl font-semibold text-ink">{{ number_format($stats['total_transactions'] ?? 0) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Total Buy Volume</p>
                <p class="text-2xl font-semibold text-ink">MYR {{ number_format($stats['total_buy_volume'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Total Sell Volume</p>
                <p class="text-2xl font-semibold text-ink">MYR {{ number_format($stats['total_sell_volume'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Net Position</p>
                <p class="text-2xl font-semibold {{ ($stats['net_position'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    MYR {{ number_format($stats['net_position'] ?? 0, 2) }}
                </p>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-ink mb-4">Currency Breakdown</h2>
            <p class="text-sm text-ink-muted mb-4">for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th class="text-left py-3 px-4 font-medium text-ink-muted">Currency</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Buy Count</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Buy Volume</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Sell Count</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Sell Volume</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Net Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary as $currency => $data)
                    <tr class="border-b border-border hover:bg-canvas-subtle">
                        <td class="py-3 px-4 text-ink font-medium">{{ $currency }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($data['buy_count']) }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($data['buy_volume'], 2) }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($data['sell_count']) }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($data['sell_volume'], 2) }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted {{ $data['net_volume'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($data['net_volume'], 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-ink-muted">No transaction data available</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Average Transaction Value</p>
                <p class="text-lg font-semibold text-ink">MYR {{ number_format($stats['avg_transaction_value'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Pending Approval</p>
                <p class="text-lg font-semibold text-ink">{{ number_format($stats['pending_approval'] ?? 0) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Report Status</p>
                <p class="text-lg font-semibold text-green-600">Complete</p>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <button onclick="window.print()" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                Print Report
            </button>
            <form method="POST" action="{{ route('reports.msb2.export', ['date' => $date]) }}">
                @csrf
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Export Report
                </button>
            </form>
        </div>
        @else
        <div class="bg-surface border border-border rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-ink mb-2">Select a Date</h3>
            <p class="text-sm text-ink-muted">Choose a date above to view the MSB2 daily transaction summary.</p>
        </div>
        @endif
    </div>
</x-app-layout>