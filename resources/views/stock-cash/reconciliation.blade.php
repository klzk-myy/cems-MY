<x-app-layout title="Till Reconciliation - {{ $date }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Till Reconciliation"
            description="Date: {{ $date }} | Till ID: {{ $tillId }}"
            :actions="true"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ url()->previous() }}">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Opening Balance">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <dt class="text-sm font-medium text-ink-muted">MYR Opening</dt>
                    <dd class="mt-1 text-lg font-semibold text-ink">
                        MYR {{ number_format((float) ($reconciliation['opening_myr'] ?? 0), 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">FCY Opening</dt>
                    <dd class="mt-1 text-lg font-semibold text-ink">
                        {{ number_format((float) ($reconciliation['opening_fcy'] ?? 0), 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Opening By</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $reconciliation['opener_name'] ?? 'N/A' }}
                    </dd>
                </div>
            </div>
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-card title="Buy Summary">
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-ink-muted">Total Buys</span>
                        <span class="text-sm font-medium text-ink">{{ $summary['total_buys'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-ink-muted">FCY Bought</span>
                        <span class="text-sm font-medium text-ink">
                            {{ number_format((float) ($summary['fcy_bought'] ?? 0), 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center border-t border-border pt-3">
                        <span class="text-sm font-medium text-ink-muted">MYR Received</span>
                        <span class="text-sm font-semibold text-ink">
                            MYR {{ number_format((float) ($summary['myr_from_buys'] ?? 0), 2) }}
                        </span>
                    </div>
                </div>
            </x-card>

            <x-card title="Sell Summary">
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-ink-muted">Total Sells</span>
                        <span class="text-sm font-medium text-ink">{{ $summary['total_sells'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-ink-muted">FCY Sold</span>
                        <span class="text-sm font-medium text-ink">
                            {{ number_format((float) ($summary['fcy_sold'] ?? 0), 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center border-t border-border pt-3">
                        <span class="text-sm font-medium text-ink-muted">MYR Paid Out</span>
                        <span class="text-sm font-semibold text-ink">
                            MYR {{ number_format((float) ($summary['myr_to_sells'] ?? 0), 2) }}
                        </span>
                    </div>
                </div>
            </x-card>
        </div>

        <x-card title="Expected vs Actual Closing">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Expected Closing</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Actual Closing</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Variance</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase tracking-wider">Status</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($reconciliation['currency_reconciliation'] ?? [] as $currencyRecon)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">{{ $currencyRecon['currency_code'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $currencyRecon['expected'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $currencyRecon['actual'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right">
                                @php
                                    $variance = (float) $currencyRecon['variance'];
                                    $isYellow = $variance != 0 && abs($variance) < 100;
                                    $isRed = abs($variance) >= 100;
                                @endphp
                                <span class="@if($isRed) text-danger-text font-semibold @elseif($isYellow) text-warning-text font-medium @else text-ink @endif">
                                    {{ number_format($variance, 2) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($variance == 0)
                                    <x-badge variant="success">Balanced</x-badge>
                                @elseif(abs($variance) < 100)
                                    <x-badge variant="warning">Warning</x-badge>
                                @else
                                    <x-badge variant="danger">Variance</x-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No currency reconciliation data available." :colspan="5" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Variance Summary">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="p-4 bg-canvas-subtle rounded-lg">
                    <dt class="text-sm font-medium text-ink-muted">Total MYR Variance</dt>
                    <dd class="mt-1 text-lg font-semibold @if(($reconciliation['total_myr_variance'] ?? 0) != 0) text-danger-text @else text-success-text @endif">
                        MYR {{ number_format((float) ($reconciliation['total_myr_variance'] ?? 0), 2) }}
                    </dd>
                </div>
                <div class="p-4 bg-canvas-subtle rounded-lg">
                    <dt class="text-sm font-medium text-ink-muted">Total FCY Variance</dt>
                    <dd class="mt-1 text-lg font-semibold @if(($reconciliation['total_fcy_variance'] ?? 0) != 0) text-danger-text @else text-success-text @endif">
                        {{ number_format((float) ($reconciliation['total_fcy_variance'] ?? 0), 2) }}
                    </dd>
                </div>
                <div class="p-4 bg-canvas-subtle rounded-lg">
                    <dt class="text-sm font-medium text-ink-muted">Reconciliation Status</dt>
                    <dd class="mt-1 text-sm">
                        @if(($reconciliation['is_balanced'] ?? false))
                            <x-badge variant="success">Balanced</x-badge>
                        @else
                            <x-badge variant="danger">Out of Balance</x-badge>
                        @endif
                    </dd>
                </div>
            </div>
        </x-card>

        <x-card title="Transactions">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Customer</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">FCY Amount</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">MYR Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Status</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($transactions as $transaction)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">{{ $transaction->id }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $transaction->created_at->format('H:i:s') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge :variant="$transaction->transaction_type->value === 'buy' ? 'info' : 'purple'">
                                    {{ ucfirst($transaction->transaction_type->value) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">
                                {{ $transaction->customer->name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $transaction->foreign_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $transaction->exchange_rate, 4) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $transaction->myr_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge
                                    :variant="match ($transaction->status->value) {
                                        'completed' => 'success',
                                        'pending_approval' => 'warning',
                                        default => 'gray',
                                    }"
                                >
                                    {{ ucfirst(str_replace('_', ' ', $transaction->status->value)) }}
                                </x-badge>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No transactions found for this till and date." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
