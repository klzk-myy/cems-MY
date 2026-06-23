<x-app-layout title="Trial Balance">
    <div class="space-y-6">
        <x-page-header title="Trial Balance" description="As of {{ $asOfDate }}" :actions="true">
            <x-slot:actions>
                <form method="GET" class="flex items-center gap-3">
                    <x-input type="date" name="as_of_date" :value="$asOfDate" inline />
                    <x-button type="submit" variant="primary">Refresh</x-button>
                </form>
            </x-slot:actions>
        </x-page-header>

        @if ($trialBalance['is_balanced'])
            <x-alert type="success" title="✓ Trial Balance is balanced">
                Total Debits: RM {{ number_format((float) $trialBalance['total_debits'], 2) }} | Total Credits: RM {{ number_format((float) $trialBalance['total_credits'], 2) }}
            </x-alert>
        @else
            <x-alert type="error" title="✗ Trial Balance is NOT balanced">
                Difference: RM {{ number_format((float) $trialBalance['total_balance'], 2) }}
            </x-alert>
        @endif

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit (RM)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit (RM)</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($trialBalance['accounts'] as $account)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono text-ink">{{ $account['account_code'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $account['account_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $account['account_type'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ (float) $account['debit'] > 0 ? 'text-ink' : 'text-ink-muted/50' }}">{{ (float) $account['debit'] > 0 ? number_format((float) $account['debit'], 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ (float) $account['credit'] > 0 ? 'text-ink' : 'text-ink-muted/50' }}">{{ (float) $account['credit'] > 0 ? number_format((float) $account['credit'], 2) : '-' }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No accounts found" :colspan="5" />
                    @endforelse
                    <tr class="font-semibold bg-canvas-subtle">
                        <td colspan="3" class="px-4 py-3 text-sm text-ink">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-ink">{{ number_format((float) $trialBalance['total_debits'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-ink">{{ number_format((float) $trialBalance['total_credits'], 2) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-stat-grid cols="3">
            @foreach (['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'] as $type)
                @if (isset($trialBalance['totals_by_type'][$type]))
                    <x-stat-card label="{{ $type }}" :value="'RM '.number_format((float) $trialBalance['totals_by_type'][$type], 2)" />
                @endif
            @endforeach
        </x-stat-grid>
    </div>
</x-app-layout>
