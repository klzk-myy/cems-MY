@extends('layouts.base')

@section('title', 'LCTR Report')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-gray-900">Large Cash Transaction Report</h1>
    <p class="text-sm text-gray-500">Monthly - {{ $month ? \Carbon\Carbon::parse($month)->format('F Y') : '' }}</p>
</div>
@endsection

@section('header-actions')
<form wire:submit="loadReport" class="flex items-center gap-2">
    <input type="month" wire:model="selectedMonth" class="form-input">
    <button type="submit" class="btn btn-secondary">View</button>
</form>
@endsection

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
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
                    <td>{{ $tx['Transaction_Date'] ?? 'N/A' }}</td>
                    <td>{{ $tx['Customer_Name'] ?? 'N/A' }}</td>
                    <td class="font-mono">{{ $tx['Customer_ID_Number'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($tx['Amount_Local'] ?? 0, 2) }}</td>
                    <td>{{ $tx['Transaction_Type'] ?? 'N/A' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-gray-500">No transactions found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection