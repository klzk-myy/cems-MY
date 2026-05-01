@extends('layouts.base')

@section('title', 'Monthly Trends')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Monthly Trends - {{ $year ?? date('Y') }}</h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Volume (MYR)</th>
                        <th class="text-right">Transactions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthlyData ?? [] as $month => $data)
                    <tr>
                        <td>{{ $month }}</td>
                        <td class="font-mono text-right">{{ number_format($data['volume'] ?? 0, 2) }}</td>
                        <td class="font-mono text-right">{{ number_format($data['count'] ?? 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection