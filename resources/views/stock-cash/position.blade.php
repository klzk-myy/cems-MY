<x-app-layout title="Currency Position - {{ $position->currency->code ?? 'N/A' }}">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-ink">Currency Position</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Position details for {{ $position->currency->code ?? 'N/A' }} - {{ $position->currency->name ?? 'N/A' }}
            </p>
        </div>

        <!-- Position Details Card -->
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <h2 class="text-lg font-medium text-ink mb-4">Position Details</h2>
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
        </div>

        <!-- Recent Buy Transactions -->
        <div class="bg-surface border border-border rounded-xl p-6">
            <h2 class="text-lg font-medium text-ink mb-4">Recent Buy Transactions (Last 50)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#e5e5e5]">
                    <thead>
                        <tr class="bg-canvas-subtle">
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">MYR Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
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
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded
                                        @if($transaction->status->value === 'completed')
                                            bg-green-100 text-green-700
                                        @elseif($transaction->status->value === 'pending_approval')
                                            bg-yellow-100 text-yellow-700
                                        @elseif($transaction->status->value === 'pending')
                                            bg-orange-100 text-orange-700
                                        @else
                                            bg-canvas-subtle text-gray-700
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $transaction->status->value)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-sm text-ink-muted">
                                    No recent buy transactions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
</x-app-layout>
