@extends('layouts.base')

@section('title', 'LCTR Report')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Large Cash Transaction Report</h1>
    <p class="text-sm text-[--color-ink-muted]">Monthly - {{ $month ?? date('F Y') }}</p>
</div>
@endsection

@section('content')
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>IC Number</th>
                    <th class="text-right">Amount (MYR)</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions ?? [] as $tx)
                <tr>
                    <td>{{ $tx['date'] ?? 'N/A' }}</td>
                    <td>{{ $tx['customer'] ?? 'N/A' }}</td>
                    <td class="font-mono">{{ $tx['ic_number'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($tx['amount'] ?? 0, 2) }}</td>
                    <td>{{ $tx['type'] ?? 'N/A' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No transactions found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
