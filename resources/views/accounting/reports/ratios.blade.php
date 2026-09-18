<x-app-layout title="Financial Ratios">
    <div class="space-y-6">
        <x-page-header title="Financial Ratios" description="Liquidity and leverage ratios derived from the trial balance" />

        <x-filter-bar>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                @if ($canSelectBranch)
                    <x-select name="branch_id" label="Branch" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->toArray()" :selected="$currentBranch?->id" inline />
                @elseif ($currentBranch)
                    <span class="self-center text-sm text-ink-muted">Branch: <span class="font-medium text-ink">{{ $currentBranch->name }}</span></span>
                @endif
                <x-input name="as_of_date" label="As of" type="date" :value="$asOfDate" inline />
                <x-button variant="secondary" type="submit">Apply</x-button>
            </form>
        </x-filter-bar>

        <x-stat-grid cols="2">
            <x-stat-card
                label="Current Ratio (assets ÷ liabilities)"
                :value="$ratios['current_ratio'] === 'N/A' ? 'N/A' : number_format((float) $ratios['current_ratio'], 2)"
            />
            <x-stat-card
                label="Debt Ratio (liabilities ÷ assets)"
                :value="$ratios['debt_ratio'] === 'N/A' ? 'N/A' : number_format((float) $ratios['debt_ratio'], 2)"
            />
        </x-stat-grid>

        <x-card title="Balance Sheet Inputs — {{ $asOfDate }}">
            <x-table>
                <x-slot:tbody>
                    @foreach ([
                        'Total Assets' => 'total_assets',
                        'Current Assets' => 'current_assets',
                        'Total Liabilities' => 'total_liabilities',
                        'Current Liabilities' => 'current_liabilities',
                    ] as $label => $key)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $label }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">RM {{ number_format((float) $ratios[$key], 2) }}</td>
                        </tr>
                    @endforeach
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Trial Balance Detail">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Balance</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse (($trialBalance['accounts'] ?? []) as $account)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">{{ $account['account_code'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $account['account_name'] ?? $account['name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $account['type'] ?? $account['account_type'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) ($account['debit'] ?? 0), 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) ($account['credit'] ?? 0), 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) ($account['balance'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No trial balance data for this date." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
