<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Account: {{ $account->code }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-4 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Account Name</label>
                    <p class="mt-1 font-medium">{{ $account->name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Type</label>
                    <p class="mt-1">{{ $account->type }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Balance</label>
                    <p class="mt-1 text-2xl font-bold">${{ number_format($balance, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Period</label>
                    <p class="mt-1">{{ $period }}</p>
                </div>
            </div>

            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Description</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Debit</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Credit</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $entry['date'] }}</td>
                        <td class="px-4 py-3">{{ $entry['description'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $entry['debit'] > 0 ? '$'.number_format($entry['debit'], 2) : '' }}</td>
                        <td class="px-4 py-3 text-right">{{ $entry['credit'] > 0 ? '$'.number_format($entry['credit'], 2) : '' }}</td>
                        <td class="px-4 py-3 text-right font-medium">${{ number_format($entry['balance'], 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No entries for this period</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>