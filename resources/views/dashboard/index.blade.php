<x-app-layout title="Dashboard">
    <x-page-header title="Dashboard" description="Overview of your exchange operations" />

    <x-stat-grid>
        <x-stat-card label="Total Transactions" :value="$stats['total_transactions'] ?? 0" color="blue" />
        <x-stat-card label="Active Customers" :value="$stats['active_customers'] ?? 0" color="green" />
        <x-stat-card label="Buy Volume" color="purple"><x-money :amount="$stats['buy_volume'] ?? 0" currency="MYR" :decimals="0" /></x-stat-card>
        <x-stat-card label="Sell Volume" color="yellow"><x-money :amount="$stats['sell_volume'] ?? 0" currency="MYR" :decimals="0" /></x-stat-card>
    </x-stat-grid>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card title="Recent Transactions">
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($recent_transactions as $transaction)
                        <tr>
                            <td class="px-4 py-3 font-mono text-sm">
                                <a href="{{ route('transactions.show', $transaction) }}" class="text-primary hover:underline">{{ $transaction->reference }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $transaction->customer->full_name ?? 'N/A' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$transaction->type?->value === 'Buy' ? 'success' : 'purple'">
                                    {{ $transaction->type?->label() ?? 'N/A' }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $transaction->amount_local, 2) }} MYR</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="match ($transaction->status) {
                                    \App\Enums\TransactionStatus::Completed => 'success',
                                    \App\Enums\TransactionStatus::Pending, \App\Enums\TransactionStatus::PendingApproval => 'warning',
                                    \App\Enums\TransactionStatus::Cancelled, \App\Enums\TransactionStatus::Failed => 'danger',
                                    default => 'gray',
                                }">
                                    {{ $transaction->status?->label() ?? 'N/A' }}
                                </x-badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-3 text-sm text-ink-muted" colspan="5">No transactions today</td>
                        </tr>
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        @if ($monitoring)
            <x-card title="System Monitoring">
                <div class="space-y-6">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-ink">Dead Letter Queue</h3>
                            <a href="{{ route('transactions.dlq') }}" class="text-xs text-primary hover:underline">Review queue</a>
                        </div>
                        <p class="mt-1 text-2xl font-semibold tabular-nums {{ $monitoring['dlq_count'] > 0 ? 'text-danger' : 'text-ink' }}">{{ $monitoring['dlq_count'] }}</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-ink">System Alerts</h3>
                            <a href="{{ route('system.alerts.index') }}" class="text-xs text-primary hover:underline">View all alerts</a>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <x-badge variant="danger">Critical: {{ $monitoring['alert_counts']['critical'] ?? 0 }}</x-badge>
                            <x-badge variant="warning">Warning: {{ $monitoring['alert_counts']['warning'] ?? 0 }}</x-badge>
                            <x-badge variant="info">Info: {{ $monitoring['alert_counts']['info'] ?? 0 }}</x-badge>
                        </div>
                        <ul class="mt-3 space-y-2">
                            @forelse ($monitoring['recent_alerts'] as $alert)
                                <li class="flex items-start gap-2 text-sm">
                                    <x-badge :variant="$alert['level'] instanceof \App\Enums\SystemAlertLevel
                                        ? ($alert['level']->value === 'critical' ? 'danger' : ($alert['level']->value === 'warning' ? 'warning' : 'info'))
                                        : ($alert['level'] === 'critical' ? 'danger' : ($alert['level'] === 'warning' ? 'warning' : 'info'))">
                                        {{ $alert['level'] instanceof \App\Enums\SystemAlertLevel ? ucfirst($alert['level']->value) : ucfirst((string) $alert['level']) }}
                                    </x-badge>
                                    <span class="min-w-0 flex-1 text-ink">{{ $alert['message'] }}</span>
                                    <a href="{{ route('system.alerts.acknowledge.show', $alert['id']) }}" class="text-xs text-primary hover:underline whitespace-nowrap">Acknowledge</a>
                                    <span class="text-xs text-ink-muted whitespace-nowrap">{{ $alert['created_at'] }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-ink-muted">No unacknowledged alerts.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
