<x-app-layout title="Dead Letter Queue">
    <div class="space-y-6">
        <x-page-header title="Dead Letter Queue" />

        <x-alert type="warning" title="Manual intervention required">
            These transactions exhausted automatic retries and were parked in the dead letter queue.
            Review the failure reason below, resolve the underlying cause, then retry them.
            Transactions that cannot be recovered can be <strong>archived</strong> to remove them from this queue;
            archived records are retained, not deleted.
        </x-alert>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Failure Reason</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Retries</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase"></th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($transactions as $transaction)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 font-mono text-sm">{{ $transaction->reference }}</td>
                            <td class="px-4 py-3">{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $transaction->customer->full_name ?? 'N/A' }}</td>
                            <td class="px-4 py-3">
                                <x-badge variant="{{ $transaction->type->value === 'Buy' ? 'success' : 'danger' }}">
                                    {{ $transaction->type->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted max-w-xs">
                                <span class="block truncate" title="{{ $transaction->failure_reason }}">
                                    {{ $transaction->failure_reason ?? $transaction->transactionErrors->first()?->error_message ?? 'Unknown error' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $transaction->transactionErrors->max('retry_count') ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-button href="{{ route('transactions.show', $transaction) }}" variant="ghost" size="sm">View</x-button>
                                    <form method="POST" action="{{ route('transactions.dlq.retry', $transaction) }}">
                                        @csrf
                                        <x-button type="submit" variant="primary" size="sm">Retry</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('transactions.dlq.purge', $transaction) }}"
                                          onsubmit="return confirm('Archive {{ $transaction->reference }} and remove it from the dead letter queue? The record is retained, not deleted.');">
                                        @csrf
                                        <x-button type="submit" variant="danger" size="sm">Archive</x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No transactions in the dead letter queue." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="mt-4">
            {{ $transactions->withQueryString()->links() }}
        </div>
    </div>
</x-app-layout>
