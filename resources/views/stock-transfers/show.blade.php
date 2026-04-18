@extends('layouts.base')

@section('title', 'Stock Transfer Details')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Stock Transfer #{{ $stockTransfer->id ?? 'N/A' }}</h3>
        <div class="flex gap-2">
            <a href="{{ route('stock-transfers.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Transfer Date</dt>
                    <dd class="font-mono">{{ $stockTransfer->transfer_date ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                    <dd>
                        @if(isset($stockTransfer->status))
                            @statuslabel($stockTransfer->status)
                        @else
                            <span class="text-[--color-ink-muted]">N/A</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Source Branch</dt>
                    <dd class="font-medium">{{ $stockTransfer->source_branch_name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Destination Branch</dt>
                    <dd class="font-medium">{{ $stockTransfer->destination_branch_name ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>

        <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Transfer Items</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Value (MYR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stockTransfer->items ?? [] as $item)
                <tr>
                    <td class="font-mono">{{ $item['currency'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($item['quantity'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">RM {{ number_format($item['value'] ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center py-8 text-[--color-ink-muted]">No items</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right font-medium">Total</td>
                    <td class="font-mono text-right">RM {{ number_format($stockTransfer->total_value ?? 0, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection