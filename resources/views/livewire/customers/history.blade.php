<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Transaction History - {{ $customer->name ?? 'N/A' }}</h3>
    </div>
    <div class="p-6">
        @if($customer->transactions->count() > 0)
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left text-sm text-[--color-ink-muted]">ID</th>
                        <th class="text-left text-sm text-[--color-ink-muted]">Date</th>
                        <th class="text-left text-sm text-[--color-ink-muted]">Type</th>
                        <th class="text-right text-sm text-[--color-ink-muted]">Amount</th>
                        <th class="text-left text-sm text-[--color-ink-muted]">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customer->transactions as $txn)
                    <tr class="border-t border-[--color-border]">
                        <td class="py-3">{{ $txn->id }}</td>
                        <td class="py-3">{{ $txn->created_at->format('Y-m-d H:i') }}</td>
                        <td class="py-3">{{ $txn->type }}</td>
                        <td class="py-3 text-right font-mono">RM {{ number_format($txn->amount_local, 2) }}</td>
                        <td class="py-3">{{ $txn->status->value ?? $txn->status }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-[--color-ink-muted]">No transactions found for this customer.</p>
        @endif
    </div>
</div>