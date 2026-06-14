<x-app-layout title="Profit & Loss">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Profit & Loss</h1>
                <p class="mt-1 text-sm text-ink-muted">{{ $from }} to {{ $to }}</p>
            </div>
            <form method="GET" class="flex items-center gap-3">
                <input type="date" name="from" value="{{ $from }}"
                       class="px-3 py-2 text-sm border border-border rounded-lg">
                <input type="date" name="to" value="{{ $to }}"
                       class="px-3 py-2 text-sm border border-border rounded-lg">
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Refresh
                </button>
            </form>
        </div>

        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-border bg-canvas-subtle">
                <h3 class="text-sm font-semibold text-ink">Revenue</h3>
            </div>
            <table class="w-full">
                <thead class="border-b border-border">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Amount (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($report['revenues'] ?? [] as $revenue)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">{{ $revenue['account_code'] }} - {{ $revenue['account_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $revenue['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-3 text-sm text-center text-ink-muted">No revenue accounts</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-canvas-subtle border-t border-border">
                    <tr class="font-semibold">
                        <td class="px-4 py-3 text-sm text-ink">Total Revenue</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-green-700">{{ number_format((float) ($report['total_revenue'] ?? '0'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-border bg-canvas-subtle">
                <h3 class="text-sm font-semibold text-ink">Expenses</h3>
            </div>
            <table class="w-full">
                <thead class="border-b border-border">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Amount (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($report['expenses'] ?? [] as $expense)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">{{ $expense['account_code'] }} - {{ $expense['account_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $expense['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-3 text-sm text-center text-ink-muted">No expense accounts</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-canvas-subtle border-t border-border">
                    <tr class="font-semibold">
                        <td class="px-4 py-3 text-sm text-ink">Total Expenses</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-red-700">{{ number_format((float) ($report['total_expenses'] ?? '0'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-surface border border-border rounded-xl p-6">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-ink">Net {{ (float) ($report['net_profit'] ?? '0') >= 0 ? 'Profit' : 'Loss' }}</p>
                <p class="text-2xl font-semibold {{ (float) ($report['net_profit'] ?? '0') >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    RM {{ number_format(abs((float) ($report['net_profit'] ?? '0')), 2) }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
