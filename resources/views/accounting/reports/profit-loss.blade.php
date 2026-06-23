<x-app-layout title="Profit & Loss">
    <div class="space-y-6">
        <x-page-header title="Profit & Loss" :actions="true">
            {{ $from }} to {{ $to }}

            <x-slot:actions>
                <form method="GET" class="flex items-center gap-3">
                    <x-input type="date" name="from" :value="$from" inline />
                    <x-input type="date" name="to" :value="$to" inline />
                    <x-button type="submit" variant="primary">Refresh</x-button>
                </form>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Revenue">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount (RM)</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($report['revenues'] ?? [] as $revenue)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">{{ $revenue['account_code'] }} - {{ $revenue['account_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $revenue['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No revenue accounts" :colspan="2" />
                    @endforelse
                    <tr class="font-semibold bg-canvas-subtle border-t border-border">
                        <td class="px-4 py-3 text-sm text-ink">Total Revenue</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-success-text">{{ number_format((float) ($report['total_revenue'] ?? '0'), 2) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Expenses">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount (RM)</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($report['expenses'] ?? [] as $expense)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">{{ $expense['account_code'] }} - {{ $expense['account_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $expense['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No expense accounts" :colspan="2" />
                    @endforelse
                    <tr class="font-semibold bg-canvas-subtle border-t border-border">
                        <td class="px-4 py-3 text-sm text-ink">Total Expenses</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-danger-text">{{ number_format((float) ($report['total_expenses'] ?? '0'), 2) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card>
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-ink">Net {{ (float) ($report['net_profit'] ?? '0') >= 0 ? 'Profit' : 'Loss' }}</p>
                <p class="text-2xl font-semibold {{ (float) ($report['net_profit'] ?? '0') >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                    RM {{ number_format(abs((float) ($report['net_profit'] ?? '0')), 2) }}
                </p>
            </div>
        </x-card>
    </div>
</x-app-layout>
