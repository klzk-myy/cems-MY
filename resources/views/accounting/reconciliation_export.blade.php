@extends('layouts.base')

@section('title', 'Export Reconciliation')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Export Reconciliation Data</h3></div>
    <div class="card-body">
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Reconciliation Summary</h4>
            <dl class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Period</dt>
                    <dd class="font-medium">{{ $report['period'] ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Bank Balance</dt>
                    <dd class="font-mono">RM {{ number_format($report['bank_balance'] ?? 0, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Book Balance</dt>
                    <dd class="font-mono">RM {{ number_format($report['book_balance'] ?? 0, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Difference</dt>
                    <dd class="font-mono @if(($report['difference'] ?? 0) != 0) text-red-600 @endif">
                        RM {{ number_format($report['difference'] ?? 0, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Outstanding Checks</dt>
                    <dd class="font-mono">{{ count($report['outstanding_checks'] ?? []) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Unrecorded Transactions</dt>
                    <dd class="font-mono">{{ count($report['bank_transactions'] ?? []) }}</dd>
                </div>
            </dl>
        </div>

        <div class="flex gap-3">
            <form method="POST" action="{{ route('accounting.reconciliation.export', $report['id'] ?? 0) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Download PDF</button>
            </form>
            <form method="POST" action="{{ route('accounting.reconciliation.export.excel', $report['id'] ?? 0) }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Download Excel</button>
            </form>
            <a href="{{ route('accounting.reconciliation') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>
@endsection