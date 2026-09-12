<x-app-layout title="Expenses">
    <div class="space-y-6">
        <x-page-header title="Petty Cash Expenses" description="Branch petty-cash expense postings">
            <x-slot:actions>
                @if (auth()->user()->role->isManager())
                    <x-button href="{{ route('accounting.expenses.create') }}" variant="primary">+ New Expense</x-button>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if(isset($currentBranch) && $currentBranch)
            <x-card>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-ink-muted">{{ $currentBranch->name }} petty cash float</span>
                    <span class="text-lg font-semibold font-mono">MYR {{ number_format((float) $pettyCashFloat, 2) }}</span>
                </div>
            </x-card>
        @endif

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount (MYR)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Posted By</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($expenses ?? [] as $expense)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $expense->expense_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $expense->branch?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $expense->category }}</td>
                            <td class="px-4 py-3 text-sm">{{ $expense->description }}</td>
                            <td class="px-4 py-3 text-sm font-mono">{{ $expense->account_code }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $expense->amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ $expense->creator?->username ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No expenses posted yet" :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>

            @if(method_exists($expenses ?? collect(), 'links'))
                <div class="mt-4">{{ $expenses->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
