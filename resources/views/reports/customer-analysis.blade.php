@extends('layouts.base')

@section('title', 'Customer Analysis')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Top Customers</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th class="text-right">Transactions</th>
                    <th class="text-right">Total Volume</th>
                    <th>Risk Level</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topCustomers ?? [] as $customer)
                <tr>
                    <td>{{ $customer['name'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($customer['transactions'] ?? 0) }}</td>
                    <td class="font-mono text-right">{{ number_format($customer['volume'] ?? 0, 2) }} MYR</td>
                    <td>
                        @php $riskClass = match($customer['risk'] ?? '') { 'High' => 'badge-danger', 'Medium' => 'badge-warning', default => 'badge-success' }; @endphp
                        <span class="badge {{ $riskClass }}">{{ $customer['risk'] ?? 'Low' }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
