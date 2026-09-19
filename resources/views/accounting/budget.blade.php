<x-app-layout title="Budget Management">
    <div class="space-y-6">
        <x-page-header title="Budget Management" description="Track budgets against actual spending per period" />

        <x-filter-bar>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <x-select name="period" :options="$periods->mapWithKeys(fn ($p) => [$p => $p])->all()" placeholder="Select period" :value="$periodCode" inline />
                <x-button variant="secondary" type="submit">Apply</x-button>
            </form>
        </x-filter-bar>

        <x-stat-grid cols="4">
            <x-stat-card label="Total Budget" :value="'RM '.number_format((float) $report['total_budget'], 2)" />
            <x-stat-card label="Total Actual" :value="'RM '.number_format((float) $report['total_actual'], 2)" />
            <x-stat-card label="Total Variance" :value="'RM '.number_format((float) $report['total_variance'], 2)" :color="(float) $report['total_variance'] < 0 ? 'red' : 'green'" />
            <x-stat-card label="Over Budget" :value="$report['over_budget_count']" :color="$report['over_budget_count'] > 0 ? 'red' : 'green'" />
        </x-stat-grid>

        @if (auth()->user()->role->canPerform(\App\Enums\Permission::ManageAccounting))
        <x-card title="Set Budget — {{ $periodCode }}">
            @if ($unbudgeted->isEmpty())
                <p class="text-sm text-ink-muted">All active expense accounts already have budgets for this period.</p>
            @else
                <form method="POST" action="{{ route('accounting.budget.store') }}" class="space-y-4"
                      x-data="budgetRows">
                    @csrf
                    <input type="hidden" name="period_code" value="{{ $periodCode }}">

                    <div class="space-y-2">
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="flex items-end gap-2">
                                <select :name="'budgets['+index+'][account_code]'" x-model="row.account_code" required
                                        class="flex-1 px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg text-ink">
                                    <option value="">Select account…</option>
                                    @foreach ($unbudgeted as $account)
                                        <option value="{{ $account->account_code }}">{{ $account->account_code }} — {{ $account->account_name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" :name="'budgets['+index+'][budget_myr]'" x-model="row.budget_myr" step="0.01" min="0" required placeholder="Amount"
                                       class="w-40 px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg text-ink">
                                <x-button type="button" variant="ghost" size="sm" @click="removeRow(index)" x-show="rows.length > 1">✕</x-button>
                            </div>
                        </template>
                    </div>

                    <div class="flex gap-3">
                        <x-button type="button" variant="secondary" @click="addRow()">+ Add Row</x-button>
                        <x-button type="submit" variant="primary">Save Budgets</x-button>
                    </div>
                </form>
            @endif
        </x-card>
        @endif

        <x-card title="Budget vs Actual — {{ $periodCode }}">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Budget</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Actual</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Variance</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Var %</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Update</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($report['items'] as $item)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">
                                <span class="font-medium text-ink">{{ $item['account_code'] }}</span>
                                <span class="text-ink-muted">— {{ $item['account_name'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $item['budget'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $item['actual'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ (float) $item['variance'] < 0 ? 'text-danger' : '' }}">{{ number_format((float) $item['variance'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ $item['variance_pct'] !== null ? number_format($item['variance_pct'], 1).'%' : '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge :variant="$item['over_budget'] ? 'danger' : 'success'">{{ $item['over_budget'] ? 'Over' : 'Within' }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if (auth()->user()->role->canPerform(\App\Enums\Permission::ManageAccounting))
                                <form method="POST" action="{{ route('accounting.budget.update', $item['id']) }}" class="flex items-center justify-center gap-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="budget_myr" step="0.01" min="0" required placeholder="New amount"
                                           class="w-28 px-2 py-1 text-xs bg-canvas-subtle border border-border rounded-lg text-ink">
                                    <x-button variant="ghost" size="sm" type="submit">Save</x-button>
                                </form>
                                @else
                                <span class="text-xs text-ink-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No budgets set for this period." :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
