@extends('layouts.base')

@section('title', 'Customer Analysis')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Top Customers</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
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
                        @php $riskClass = match($customer['risk'] ?? '') { 'High' => 'bg-red-100 text-red-700', 'Medium' => 'bg-yellow-100 text-yellow-700', default => 'bg-green-100 text-green-700' }; @endphp
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $riskClass }}">{{ $customer['risk'] ?? 'Low' }}</span>
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