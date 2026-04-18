@extends('layouts.base')

@section('title', 'Ledger')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Account Ledger</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Account Code</th>
                    <th>Account Name</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($trialBalance ?? [] as $account)
                <tr>
                    <td class="font-mono">{{ $account['account_code'] ?? 'N/A' }}</td>
                    <td>{{ $account['account_name'] ?? 'N/A' }}</td>
                    <td class="font-mono">{{ number_format($account['debit'] ?? 0, 2) }}</td>
                    <td class="font-mono">{{ number_format($account['credit'] ?? 0, 2) }}</td>
                    <td class="font-mono font-medium">{{ number_format($account['balance'] ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No ledger data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
