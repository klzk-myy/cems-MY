<x-app-layout title="Currency Position - {{ $position->currency->code ?? 'N/A' }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Currency Position"
            description="Position details for {{ $position->currency->code ?? 'N/A' }} - {{ $position->currency->name ?? 'N/A' }}"
        />

        <x-card title="Position Details">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Currency</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $position->currency->code ?? 'N/A' }} - {{ $position->currency->name ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Opening Balance</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ number_format((float) $position->opening_balance, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Current Balance</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ number_format((float) $position->current_balance, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Available Balance</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ number_format((float) $position->getAvailableBalance(), 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Reserved Amount</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ number_format((float) $position->reserved_amount, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Branch</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $position->branch->name ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Last Updated</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $position->updated_at ? $position->updated_at->format('d M Y H:i:s') : 'N/A' }}
                    </dd>
                </div>
            </div>
        </x-card>

        <x-card title="Recent Buy Transactions (Last 50)">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Amount</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">MYR Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Status</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($transactions as $transaction)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">{{ $transaction->id }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $transaction->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm text-ink">
                                {{ $transaction->customer->name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">
                                {{ $transaction->currency->code ?? 'N/A' }}
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
                                        'pending' => 'warning',
                                        default => 'gray',
                                    }"
                                >
                                    {{ ucfirst(str_replace('_', ' ', $transaction->status->value)) }}
                                </x-badge>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No recent buy transactions found." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
