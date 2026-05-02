@extends('layouts.base')

@section('title', 'Trial Balance - CEMS-MY')

@section('content')
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Trial Balance</h1>
        <p class="text-sm text-gray-500">All accounts with debit/credit balances</p>
    </div>

    {{-- Date Filter --}}
    <div class="card mb-6">
        <div class="card-body flex items-center gap-4">
            <label class="text-sm font-medium">As of Date:</label>
            <input type="date" wire:model.live="asOfDate" class="input w-auto" />
        </div>
    </div>

    {{-- Trial Balance Table --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Trial Balance</h3>
            <span class="text-sm text-gray-500">As of {{ $asOfDate }}</span>
        </div>
        <div class="table-container">
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
                        <td class="font-mono text-right">{{ number_format((float) ($account['debit'] ?? 0), 2) }}</td>
                        <td class="font-mono text-right">{{ number_format((float) ($account['credit'] ?? 0), 2) }}</td>
                    </tr>
                    @php
                        $totalDebit += (float) ($account['debit'] ?? 0);
                        $totalCredit += (float) ($account['credit'] ?? 0);
                    @endphp
                    @empty
                    <tr><td colspan="4" class="text-center py-8 text-gray-500">No accounts found</td></tr>
                    @endforelse
                    <tr class="font-semibold bg-gray-100">
                        <td colspan="2">Total</td>
                        <td class="font-mono text-right">{{ number_format($totalDebit, 2) }}</td>
                        <td class="font-mono text-right">{{ number_format($totalCredit, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Balance Status --}}
        @if(isset($trialBalance['is_balanced']))
        <div class="card-footer">
            @if($trialBalance['is_balanced'])
            <div class="alert alert-success">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Trial balance is balanced (Debits = Credits)</span>
            </div>
            @else
            <div class="alert alert-danger">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span>Trial balance is NOT balanced - investigate discrepancy</span>
            </div>
            @endif
        </div>
        @endif
    </div>
@endsection