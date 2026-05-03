<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">General Ledger</h1>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex gap-4">
                <select wire:model="selectedAccount" class="rounded border border-[var(--color-border)] px-3 py-2">
                    <option value="">Select Account</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                    @endforeach
                </select>
                <input type="month" wire:model="period" class="rounded border border-[var(--color-border)] px-3 py-2" />
                <button wire:click="filter" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Filter</button>
            </div>
        </div>

        @if($selectedAccount)
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Account: {{ $account->code }} - {{ $account->name }}</h2>

            <div class="grid grid-cols-4 gap-6 mb-6 p-4 bg-gray-50 rounded">
                <div>
                    <p class="text-sm text-gray-500">Opening Balance</p>
                    <p class="text-xl font-bold">${{ number_format($openingBalance, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Debit</p>
                    <p class="text-xl font-bold text-red-600">${{ number_format($totalDebit, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Credit</p>
                    <p class="text-xl font-bold text-green-600">${{ number_format($totalCredit, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Closing Balance</p>
                    <p class="text-xl font-bold">${{ number_format($closingBalance, 2) }}</p>
                </div>
            </div>

            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Entry #</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Description</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Debit</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Credit</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgerEntries as $entry)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $entry->date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $entry->entry_number }}</td>
                        <td class="px-4 py-3">{{ Str::limit($entry->description, 30) }}</td>
                        <td class="px-4 py-3 text-right">{{ $entry->debit > 0 ? '$'.number_format($entry->debit, 2) : '' }}</td>
                        <td class="px-4 py-3 text-right">{{ $entry->credit > 0 ? '$'.number_format($entry->credit, 2) : '' }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($entry->running_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center">No entries found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>