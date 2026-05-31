<x-app-layout title="Account Ledger">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Account Ledger</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $ledger['account']->account_code }} - {{ $ledger['account']->account_name }}</p>
            </div>
            <form method="GET" class="flex items-center gap-3">
                <input type="date" name="from" value="{{ $from }}"
                       class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-lg">
                <input type="date" name="to" value="{{ $to }}"
                       class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-lg">
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Refresh
                </button>
            </form>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-xs font-medium text-gray-500 uppercase">Opening Balance</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">RM {{ number_format((float) ($ledger['opening_balance'] ?? '0'), 2) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-xs font-medium text-gray-500 uppercase">Closing Balance</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">RM {{ number_format((float) ($ledger['closing_balance'] ?? '0'), 2) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-xs font-medium text-gray-500 uppercase">Account Type</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ $ledger['account']->account_type }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit (RM)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit (RM)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    @forelse ($ledger['entries'] as $entry)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono">{{ $entry->entry_date }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $entry->description ?? $entry?->journalEntry?->description ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $entry->running_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-sm text-center text-gray-500">No entries for this period</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                    <tr class="font-semibold">
                        <td colspan="2" class="px-4 py-3 text-sm text-gray-900">Period Totals</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $ledger['total_debits'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $ledger['total_credits'], 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
