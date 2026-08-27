<x-app-layout title="Dashboard">
    <div class="space-y-6">
        <x-page-header title="Dashboard" />

        @if(($stats['dlq_count'] ?? 0) > 0)
            <x-alert type="warning" title="Dead Letter Queue">
                <strong>{{ $stats['dlq_count'] }}</strong> transaction(s) are stuck in the dead letter queue and need manual review.
                <a href="{{ route('transactions.dlq') }}" class="font-medium underline">Review now</a>
            </x-alert>
        @endif

        <x-stat-grid :cols="4" class="mb-8">
            <x-stat-card label="Today's Transactions" :value="$stats['total_transactions'] ?? 0" />
            <x-stat-card label="Buy Volume" :value="number_format($stats['buy_volume'] ?? 0, 2)" color="green" />
            <x-stat-card label="Sell Volume" :value="number_format($stats['sell_volume'] ?? 0, 2)" color="red" />
            <x-stat-card label="Open Flags" :value="$stats['flagged'] ?? 0" color="yellow" />
        </x-stat-grid>

        @if($monitoring)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                {{-- Dead Letter Queue --}}
                <x-card title="Dead Letter Queue" description="Transactions that exhausted automatic retries">
                    <div class="flex items-center justify-between">
                        <p class="text-4xl font-bold tabular-nums {{ ($monitoring['dlq_count'] ?? 0) > 0 ? 'text-warning' : 'text-success' }}">
                            {{ $monitoring['dlq_count'] }}
                        </p>
                        <a href="{{ route('transactions.dlq') }}" class="text-sm font-medium text-primary hover:underline">
                            Review queue
                        </a>
                    </div>
                </x-card>

                {{-- System Alerts --}}
                <x-card title="System Alerts" description="Unacknowledged alerts needing attention">
                    <div class="grid grid-cols-2 gap-4">
                        <a href="{{ route('system.alerts.index', ['level' => 'critical']) }}" class="group block">
                            <p class="text-sm text-ink-muted">Critical</p>
                            <p class="mt-1 text-3xl font-bold tabular-nums group-hover:underline {{ ($monitoring['alert_counts']['critical'] ?? 0) > 0 ? 'text-danger' : 'text-ink' }}">
                                {{ $monitoring['alert_counts']['critical'] ?? 0 }}
                            </p>
                        </a>
                        <a href="{{ route('system.alerts.index', ['level' => 'warning']) }}" class="group block">
                            <p class="text-sm text-ink-muted">Warning</p>
                            <p class="mt-1 text-3xl font-bold tabular-nums group-hover:underline {{ ($monitoring['alert_counts']['warning'] ?? 0) > 0 ? 'text-warning' : 'text-ink' }}">
                                {{ $monitoring['alert_counts']['warning'] ?? 0 }}
                            </p>
                        </a>
                    </div>
                    <div class="mt-4 pt-4 border-t border-border">
                        <a href="{{ route('system.alerts.index') }}" class="text-sm font-medium text-primary hover:underline">
                            View all alerts
                        </a>
                    </div>
                </x-card>

                {{-- Recent Alerts --}}
                <x-card title="Recent Alerts" description="Latest unacknowledged system alerts">
                    @if(empty($monitoring['recent_alerts']))
                        <p class="text-sm text-ink-muted">No unacknowledged alerts.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach(array_slice($monitoring['recent_alerts'], 0, 4) as $alert)
                                <li class="text-sm flex items-start gap-2">
                                    <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 status-dot status-{{ $alert['level']->value }}"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate" title="{{ $alert['message'] }}">{{ $alert['message'] }}</span>
                                        <span class="block text-xs text-ink-muted">
                                            {{ $alert['created_at'] }}@if(! empty($alert['source'])) · {{ $alert['source'] }}@endif
                                        </span>
                                    </span>
                                    <form method="POST" action="{{ route('system.alerts.acknowledge', $alert['id']) }}">
                                        @csrf
                                        <x-button type="submit" variant="ghost" size="sm">Ack</x-button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>
        @endif

        <x-card title="Recent Transactions">
            @if($recent_transactions->isEmpty())
                <p class="p-6 text-ink-muted">No transactions today.</p>
            @else
                <x-table>
                    <x-slot:thead>
                        <tr class="text-left text-ink-muted text-sm">
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Rate</th>
                        </tr>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @foreach($recent_transactions as $transaction)
                            <tr class="border-t border-border">
                                <td class="px-4 py-3">{{ $transaction->created_at->format('H:i') }}</td>
                                <td class="px-4 py-3">{{ $transaction->customer?->full_name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">
                                    <x-badge variant="{{ $transaction->type->value === 'Buy' ? 'success' : 'danger' }}">
                                        {{ $transaction->type->label() }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</td>
                                <td class="px-4 py-3">{{ number_format($transaction->rate, 4) }}</td>
                            </tr>
                        @endforeach
                    </x-slot:tbody>
                </x-table>
            @endif
        </x-card>
    </div>
</x-app-layout>
