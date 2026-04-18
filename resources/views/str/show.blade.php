@extends('layouts.base')

@section('title', 'STR Details')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">STR #{{ $str->id ?? 'N/A' }}</h3>
        <div class="flex gap-2">
            <a href="{{ route('str.edit', $str->id ?? 0) }}" class="btn btn-secondary">Edit</a>
            <a href="{{ route('str.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                    <dd>
                        @if(isset($str->status))
                            @statuslabel($str->status)
                        @else
                            <span class="text-[--color-ink-muted]">N/A</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Report Date</dt>
                    <dd class="font-mono">{{ $str->report_date ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Customer</dt>
                    <dd class="font-medium">{{ $str->customer_name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Amount</dt>
                    <dd class="font-mono">RM {{ number_format($str->amount ?? 0, 2) }}</dd>
                </div>
            </dl>
        </div>

        <div class="mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-2">Description</h4>
            <p class="text-[--color-ink]">{{ $str->description ?? 'N/A' }}</p>
        </div>

        <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Related Transactions</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Currency</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions ?? [] as $tx)
                <tr>
                    <td class="font-mono">{{ $tx['date'] ?? 'N/A' }}</td>
                    <td>
                        <span class="badge @if(($tx['type'] ?? '') === 'Buy') badge-success @else badge-warning @endif">
                            {{ $tx['type'] ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="font-mono">{{ $tx['currency'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($tx['amount'] ?? 0, 2) }}</td>
                    <td>
                        @if(isset($tx['status']))
                            @statuslabel($tx['status'])
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No related transactions</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection