<x-app-layout title="Bank Reconciliation">
    <div class="space-y-6">
        <x-page-header title="Bank Reconciliation" description="Reconcile bank statement lines against the general ledger">
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('accounting.reconciliation.report', ['account_code' => request('account_code', $cashAccounts->first()?->account_code), 'from' => request('from', now()->startOfMonth()->toDateString()), 'to' => request('to', now()->endOfMonth()->toDateString())]) }}">View Report</x-button>
                <x-button variant="secondary" href="{{ route('accounting.reconciliation.export', ['account_code' => request('account_code', $cashAccounts->first()?->account_code), 'from' => request('from', now()->startOfMonth()->toDateString()), 'to' => request('to', now()->endOfMonth()->toDateString())]) }}">Export</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <x-select name="account_code" :options="$cashAccounts->pluck('account_name', 'account_code')->all()" placeholder="Select account" :value="request('account_code', $cashAccounts->first()?->account_code)" inline />
                <x-input name="from" type="date" :value="request('from', now()->startOfMonth()->toDateString())" inline />
                <x-input name="to" type="date" :value="request('to', now()->endOfMonth()->toDateString())" inline />
                <x-button variant="secondary" type="submit">Apply</x-button>
            </form>
        </x-filter-bar>

        <x-stat-grid cols="4">
            <x-stat-card label="Book Balance" :value="'RM '.number_format((float) $report['book_balance'], 2)" />
            <x-stat-card label="Outstanding Checks" :value="'RM '.number_format((float) $report['outstanding_checks'], 2)" />
            <x-stat-card label="Deposits in Transit" :value="'RM '.number_format((float) $report['outstanding_deposits'], 2)" />
            <x-stat-card label="Adjusted Balance" :value="'RM '.number_format((float) $report['adjusted_balance'], 2)" color="green" />
        </x-stat-grid>

        @php($canManageAccounting = auth()->user()->role->canPerform(\App\Enums\Permission::ManageAccounting))

        @if ($canManageAccounting)
        <x-card title="Import Bank Statement">
            <form method="POST" action="{{ route('accounting.reconciliation.import') }}" class="space-y-4"
                  x-data="reconciliationLines">
                @csrf
                <x-select name="account_code" label="Account" :options="$cashAccounts->pluck('account_name', 'account_code')->all()" required placeholder="Select account" />

                <div class="space-y-2">
                    <template x-for="(line, index) in lines" :key="index">
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
                            <input type="date" :name="'lines['+index+'][date]'" x-model="line.date" required
                                   class="px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg text-ink">
                            <input type="text" :name="'lines['+index+'][reference]'" x-model="line.reference" placeholder="Reference"
                                   class="px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg text-ink">
                            <input type="text" :name="'lines['+index+'][description]'" x-model="line.description" placeholder="Description" required
                                   class="px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg text-ink md:col-span-2">
                            <input type="number" :name="'lines['+index+'][debit]'" x-model="line.debit" step="0.01" min="0" placeholder="Debit"
                                   class="px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg text-ink">
                            <div class="flex gap-2">
                                <input type="number" :name="'lines['+index+'][credit]'" x-model="line.credit" step="0.01" min="0" placeholder="Credit"
                                       class="w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg text-ink">
                                <x-button type="button" variant="ghost" size="sm" @click="removeLine(index)" x-show="lines.length > 1">✕</x-button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex gap-3">
                    <x-button type="button" variant="secondary" @click="addLine()">+ Add Line</x-button>
                    <x-button type="submit" variant="primary">Import Statement</x-button>
                </div>
            </form>
        </x-card>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Outstanding Checks (unmatched debits)">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse ($report['outstanding_checks_list'] as $item)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm">{{ $item['date'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $item['reference'] ?? $item['description'] }}</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $item['amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($canManageAccounting)
                                    <div class="flex items-center justify-center gap-1">
                                        <form method="POST" action="{{ route('accounting.reconciliation.match', $item['id']) }}" class="flex gap-1">
                                            @csrf
                                            <input type="number" name="journal_entry_id" placeholder="JE ID" required
                                                   class="w-20 px-2 py-1 text-xs bg-canvas-subtle border border-border rounded-lg text-ink">
                                            <x-button variant="ghost" size="sm" type="submit">Match</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('accounting.reconciliation.exception', $item['id']) }}"
                                              data-confirm="Mark as exception?">
                                            @csrf
                                            <input type="hidden" name="reason" value="Requires investigation">
                                            <x-button variant="ghost" size="sm" type="submit">Exception</x-button>
                                        </form>
                                    </div>
                                    @else
                                    <span class="text-xs text-ink-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No outstanding checks." :colspan="4" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>

            <x-card title="Deposits in Transit (unmatched credits)">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse ($report['outstanding_deposits_list'] as $item)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm">{{ $item['date'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $item['reference'] ?? $item['description'] }}</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $item['amount'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($canManageAccounting)
                                    <div class="flex items-center justify-center gap-1">
                                        <form method="POST" action="{{ route('accounting.reconciliation.match', $item['id']) }}" class="flex gap-1">
                                            @csrf
                                            <input type="number" name="journal_entry_id" placeholder="JE ID" required
                                                   class="w-20 px-2 py-1 text-xs bg-canvas-subtle border border-border rounded-lg text-ink">
                                            <x-button variant="ghost" size="sm" type="submit">Match</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('accounting.reconciliation.exception', $item['id']) }}"
                                              data-confirm="Mark as exception?">
                                            @csrf
                                            <input type="hidden" name="reason" value="Requires investigation">
                                            <x-button variant="ghost" size="sm" type="submit">Exception</x-button>
                                        </form>
                                    </div>
                                    @else
                                    <span class="text-xs text-ink-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No deposits in transit." :colspan="4" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>
        </div>
    </div>
</x-app-layout>
