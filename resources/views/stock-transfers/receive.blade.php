@extends('layouts.base')

@section('title', 'Receive Stock Transfer')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Receive Stock Transfer</h3>
    </div>
    <div class="p-6">
        @if(isset($transfer))
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-[--color-ink] mb-2">Receive Stock Transfer</h1>
            <p class="text-[--color-ink-muted]">Transfer #{{ $transfer->id ?? $transfer->reference }}</p>
        </div>

        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink] mb-4">Transfer Details</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">From Branch</dt>
                    <dd class="font-medium">{{ $transfer->fromBranch->code ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">To Branch</dt>
                    <dd class="font-medium">{{ $transfer->toBranch->code ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                    <dd>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                            {{ $transfer->status ?? 'In Transit' }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Dispatched By</dt>
                    <dd class="font-medium">{{ $transfer->dispatcher->username ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>

        @if(isset($items) && $items->count() > 0)
        <h4 class="text-sm font-medium text-[--color-ink] mb-3">Items Received</h4>
        <table class="w-full mb-6">
            <thead>
                <tr>
                    <th class="text-left text-sm text-[--color-ink-muted]">Currency</th>
                    <th class="text-right text-sm text-[--color-ink-muted]">Denomination</th>
                    <th class="text-right text-sm text-[--color-ink-muted]">Quantity Sent</th>
                    <th class="text-right text-sm text-[--color-ink-muted]">Quantity Received</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr class="border-t border-[--color-border-subtle]">
                    <td class="py-2 font-mono">{{ $item->currency_code ?? 'N/A' }}</td>
                    <td class="py-2 text-right font-mono">{{ number_format($item->denomination ?? 0, 2) }}</td>
                    <td class="py-2 text-right font-mono">{{ $item->quantity_sent ?? 0 }}</td>
                    <td class="py-2 text-right font-mono">
                        <input type="number" name="received[{{ $item->id }}]" value="{{ $item->quantity_sent ?? 0 }}" min="0" class="w-20 px-2 py-1 text-sm text-right border border-[--color-border] rounded">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <form method="POST" action="{{ route('stock-transfers.receive', $transfer->id ?? 0) }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-[--color-ink] mb-1">Receiving Note</label>
                <textarea name="note" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2" placeholder="Note any discrepancies"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700">
                    Confirm Receipt
                </button>
                <a href="{{ route('stock-transfers.show', $transfer->id ?? 0) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">
                    Back
                </a>
            </div>
        </form>
        @else
        <p class="text-[--color-ink-muted]">Transfer not found.</p>
        <a href="{{ route('stock-transfers.index') }}" class="btn btn-secondary mt-4 inline-block">Back to Stock Transfers</a>
        @endif
    </div>
</div>
@endsection