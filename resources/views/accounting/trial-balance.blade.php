@extends('layouts.base')

@section('title', 'Trial Balance')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Trial Balance</h3>
        <span class="text-sm text-[--color-ink-muted]">As of {{ $asOfDate ?? date('d M Y') }}</span>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Account Code</th>
                    <th>Account Name</th>
                    <th class="text-right">Debit (MYR)</th>
                    <th class="text-right">Credit (MYR)</th>
                </tr>
            </thead>
            <tbody>
                @php $totalDebit = 0; $totalCredit = 0; @endphp
                @forelse($trialBalance['accounts'] ?? [] as $account)
                <tr>
                    <td class="font-mono">{{ $account['account_code'] }}</td>
                    <td>{{ $account['account_name'] }}</td>
                    <td class="font-mono text-right">{{ number_format($account['debit'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($account['credit'] ?? 0, 2) }}</td>
                </tr>
                @php $totalDebit += $account['debit'] ?? 0; $totalCredit += $account['credit'] ?? 0; @endphp
                @empty
                <tr><td colspan="4" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
                @endforelse
                 <tr class="font-semibold bg-[--color-canvas-subtle]">
                    <td colspan="2">Total</td>
                    <td class="font-mono text-right">{{ number_format($trialBalance['total_debits'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($trialBalance['total_credits'] ?? 0, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection