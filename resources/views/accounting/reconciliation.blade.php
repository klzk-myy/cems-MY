@extends('layouts.base')

@section('title', 'Bank Reconciliation')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Bank Reconciliation</h3>
        <span class="text-sm text-[--color-ink-muted]">{{ $fromDate ?? '' }} - {{ $toDate ?? '' }}</span>
    </div>
    <div class="card-body">
        @if($report)
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <p class="stat-card-label">Book Balance</p>
                <p class="stat-card-value">{{ number_format($report['book_balance'] ?? 0, 2) }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Adjusted Balance</p>
                <p class="stat-card-value">{{ number_format($report['adjusted_balance'] ?? 0, 2) }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Outstanding Checks</p>
                <p class="stat-card-value">{{ number_format($report['outstanding_checks'] ?? 0, 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8">
            <div>
                <h4 class="font-semibold mb-4">Outstanding Checks</h4>
                @forelse($report['outstanding_checks_list'] ?? [] as $check)
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span>{{ $check['date'] }} - {{ $check['reference'] }}</span>
                    <span class="font-mono">{{ number_format($check['amount'], 2) }}</span>
                </div>
                @empty
                <p class="text-[--color-ink-muted]">No outstanding checks</p>
                @endforelse
            </div>
            <div>
                <h4 class="font-semibold mb-4">Outstanding Deposits</h4>
                @forelse($report['outstanding_deposits_list'] ?? [] as $deposit)
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span>{{ $deposit['date'] }} - {{ $deposit['reference'] }}</span>
                    <span class="font-mono">{{ number_format(abs($deposit['amount']), 2) }}</span>
                </div>
                @empty
                <p class="text-[--color-ink-muted]">No outstanding deposits</p>
                @endforelse
            </div>
        </div>
        @else
        <p class="text-center py-8 text-[--color-ink-muted]">No reconciliation data</p>
        @endif
    </div>
</div>
@endsection