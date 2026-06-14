<x-app-layout title="General Ledger">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">General Ledger</h1>
                <p class="mt-1 text-sm text-ink-muted">View account ledger entries</p>
            </div>
        </div>

        <form method="GET" class="bg-surface border border-border rounded-xl p-4">
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-ink-muted mb-1">Account</label>
                    <select name="account_code" class="w-full px-3 py-2 text-sm border border-border rounded-lg">
                        <option value="">Select account...</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->account_code }}" {{ $accountCode === $account->account_code ? 'selected' : '' }}>
                                {{ $account->account_code }} - {{ $account->account_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted mb-1">From</label>
                    <input type="date" name="from" value="{{ $from }}"
                           class="w-full px-3 py-2 text-sm border border-border rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted mb-1">To</label>
                    <input type="date" name="to" value="{{ $to }}"
                           class="w-full px-3 py-2 text-sm border border-border rounded-lg">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                        Search
                    </button>
                </div>
            </div>
        </form>

        @if ($ledger)
            <div class="bg-surface border border-border rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-border bg-canvas-subtle">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-ink">
                            {{ $ledger['account']->account_code }} - {{ $ledger['account']->account_name }}
                        </h3>
                        <span class="text-xs text-ink-muted">{{ $ledger['account']->account_type }}</span>
                    </div>
                    <div class="mt-1 flex items-center gap-4 text-xs text-ink-muted">
                        <span>Opening: RM {{ number_format((float) ($ledger['opening_balance'] ?? '0'), 2) }}</span>
                        <span>Closing: RM {{ number_format((float) ($ledger['closing_balance'] ?? '0'), 2) }}</span>
                    </div>
                </div>
                <table class="w-full">
                    <thead class="border-b border-border">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Debit (RM)</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Credit (RM)</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Balance (RM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse ($ledger['entries'] as $entry)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm font-mono">{{ $entry->entry_date }}</td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $entry->description ?? $entry?->journalEntry?->description ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $entry->running_balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-sm text-center text-ink-muted">No entries found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-surface border border-border rounded-xl p-12 text-center">
                <p class="text-sm text-ink-muted">Select an account and date range to view ledger entries</p>
            </div>
        @endif
    </div>
</x-app-layout>
