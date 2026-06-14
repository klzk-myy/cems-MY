<x-app-layout title="Trial Balance">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Trial Balance</h1>
                <p class="mt-1 text-sm text-ink-muted">As of {{ $asOfDate }}</p>
            </div>
            <div class="flex items-center gap-3">
                <form method="GET" class="flex items-center gap-3">
                    <input type="date" name="as_of_date" value="{{ $asOfDate }}"
                           class="px-3 py-2 text-sm border border-border rounded-lg">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                        Refresh
                    </button>
                </form>
            </div>
        </div>

        @if ($trialBalance['is_balanced'])
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm font-medium text-green-800">✓ Trial Balance is balanced</p>
                <p class="text-sm text-green-600">Total Debits: RM {{ number_format((float) $trialBalance['total_debits'], 2) }} | Total Credits: RM {{ number_format((float) $trialBalance['total_credits'], 2) }}</p>
            </div>
        @else
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm font-medium text-red-800">✗ Trial Balance is NOT balanced</p>
                <p class="text-sm text-red-600">Difference: RM {{ number_format((float) $trialBalance['total_balance'], 2) }}</p>
            </div>
        @endif

        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-canvas-subtle border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit (RM)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($trialBalance['accounts'] as $account)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono text-ink">{{ $account['account_code'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $account['account_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $account['account_type'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ (float) $account['debit'] > 0 ? 'text-ink' : 'text-ink-muted/50' }}">{{ (float) $account['debit'] > 0 ? number_format((float) $account['debit'], 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ (float) $account['credit'] > 0 ? 'text-ink' : 'text-ink-muted/50' }}">{{ (float) $account['credit'] > 0 ? number_format((float) $account['credit'], 2) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-sm text-center text-ink-muted">No accounts found</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-canvas-subtle border-t border-border">
                    <tr class="font-semibold">
                        <td colspan="3" class="px-4 py-3 text-sm text-ink">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-ink">{{ number_format((float) $trialBalance['total_debits'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-ink">{{ number_format((float) $trialBalance['total_credits'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="grid grid-cols-3 gap-4">
            @foreach (['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'] as $type)
                @if (isset($trialBalance['totals_by_type'][$type]))
                    <div class="bg-surface border border-border rounded-xl p-4">
                        <p class="text-xs font-medium text-ink-muted uppercase">{{ $type }}</p>
                        <p class="mt-1 text-lg font-semibold text-ink">RM {{ number_format((float) $trialBalance['totals_by_type'][$type], 2) }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</x-app-layout>
