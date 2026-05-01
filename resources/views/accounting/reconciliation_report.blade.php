@extends('layouts.base')

@section('title', 'Reconciliation Report')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border] flex justify-between items-center">
        <h3 class="text-base font-semibold text-[--color-ink]">Reconciliation Report - {{ $report['period'] ?? 'N/A' }}</h3>
        <a href="{{ route('accounting.reconciliation') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Back</a>
    </div>
    <div class="p-6">
        <div class="mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Summary</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 bg-[--color-canvas-subtle] rounded">
                    <dt class="text-sm text-[--color-ink-muted]">Bank Balance</dt>
                    <dd class="text-xl font-mono">RM {{ number_format($report['bank_balance'] ?? 0, 2) }}</dd>
                </div>
                <div class="p-4 bg-[--color-canvas-subtle] rounded">
                    <dt class="text-sm text-[--color-ink-muted]">Book Balance</dt>
                    <dd class="text-xl font-mono">RM {{ number_format($report['book_balance'] ?? 0, 2) }}</dd>
                </div>
                <div class="p-4 bg-[--color-canvas-subtle] rounded">
                    <dt class="text-sm text-[--color-ink-muted]">Difference</dt>
                    <dd class="text-xl font-mono @if(($report['difference'] ?? 0) != 0) text-red-600 @endif">
                        RM {{ number_format($report['difference'] ?? 0, 2) }}
                    </dd>
                </div>
                <div class="p-4 bg-[--color-canvas-subtle] rounded">
                    <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                    <dd>
                        @if(($report['difference'] ?? 0) == 0)
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Reconciled</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Outstanding</span>
                        @endif
                    </dd>
                </div>
            </div>
        </div>

        @if(!empty($report['outstanding_checks']))
        <div class="mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Outstanding Checks</h4>
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th>Check No.</th>
                        <th>Date</th>
                        <th>Payee</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['outstanding_checks'] as $check)
                    <tr>
                        <td class="font-mono">{{ $check['check_number'] ?? 'N/A' }}</td>
                        <td class="font-mono">{{ $check['date'] ?? 'N/A' }}</td>
                        <td>{{ $check['payee'] ?? 'N/A' }}</td>
                        <td class="font-mono text-right">RM {{ number_format($check['amount'] ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(!empty($report['bank_transactions']))
        <div class="mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Bank Transactions Not in Books</h4>
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['bank_transactions'] as $tx)
                    <tr>
                        <td class="font-mono">{{ $tx['date'] ?? 'N/A' }}</td>
                        <td>{{ $tx['description'] ?? 'N/A' }}</td>
                        <td class="font-mono text-right">{{ number_format($tx['debit'] ?? 0, 2) }}</td>
                        <td class="font-mono text-right">{{ number_format($tx['credit'] ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection