<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Budget</h1>
            <button wire:click="create" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">New Budget</button>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex gap-4 mb-6">
                <select wire:model="fiscalYear" class="rounded border border-[var(--color-border)] px-3 py-2">
                    @foreach($fiscalYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                    @endforeach
                </select>
                <button wire:click="filter" class="px-4 py-2 border border-[var(--color-border)] rounded">Filter</button>
            </div>

            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Account</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Budget</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Actual</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Variance</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">% Used</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($budgetItems as $item)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $item->account->name }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($item->budget_amount, 2) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($item->actual_amount, 2) }}</td>
                        <td class="px-4 py-3 text-right {{ $item->variance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            ${{ number_format($item->variance, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="{{ $item->percent_used > 100 ? 'text-red-600' : ($item->percent_used > 80 ? 'text-yellow-600' : 'text-green-600') }}">
                                {{ number_format($item->percent_used, 1) }}%
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No budget items found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>