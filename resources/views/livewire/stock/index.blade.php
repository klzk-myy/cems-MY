@extends('layouts.base')

@section('title', 'Stock & Cash - CEMS-MY')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#171717]">Stock & Cash</h1>
            <p class="text-sm text-[#6b6b6b] mt-1">Currency position and cash management</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-green-600/10 rounded-lg flex items-center justify-center">
                    <span class="font-bold text-green-600">MYR</span>
                </div>
            </div>
            <p class="text-sm text-[#6b6b6b]">Cash in Hand</p>
            <p class="text-2xl font-semibold text-[#171717]">{{ number_format((float) $myrCashInHand, 2) }}</p>
            <p class="text-xs text-[#6b6b6b] mt-1">Ringgit Malaysia</p>
        </div>

        @foreach($positions as $position)
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center">
                    <span class="font-bold text-blue-500">{{ $position['currency_code'] }}</span>
                </div>
            </div>
            <p class="text-sm text-[#6b6b6b]">{{ $position['currency_name'] }}</p>
            <p class="text-2xl font-semibold text-[#171717]">{{ number_format((float) $position['quantity'], 2) }}</p>
            <p class="text-xs text-[#6b6b6b] mt-1">Avg Cost: {{ number_format((float) $position['avg_cost'], 4) }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="p-4 bg-[#f7f7f8] rounded-xl">
            <dt class="text-sm text-[#6b6b6b]">Active Positions</dt>
            <dd class="text-2xl font-mono text-[#171717]">{{ $stats['active_positions'] ?? 0 }}</dd>
        </div>
        <div class="p-4 bg-[#f7f7f8] rounded-xl">
            <dt class="text-sm text-[#6b6b6b]">Open Tills</dt>
            <dd class="text-2xl font-mono text-[#171717]">{{ $stats['open_tills'] ?? 0 }}</dd>
        </div>
        <div class="p-4 bg-[#f7f7f8] rounded-xl">
            <dt class="text-sm text-[#6b6b6b]">Closed Tills</dt>
            <dd class="text-2xl font-mono text-[#171717]">{{ $stats['closed_tills'] ?? 0 }}</dd>
        </div>
        <div class="p-4 bg-[#f7f7f8] rounded-xl">
            <dt class="text-sm text-[#6b6b6b]">Total Variance</dt>
            <dd class="text-2xl font-mono text-[#171717] {{ ((float) ($stats['total_variance'] ?? 0)) != 0 ? 'text-red-600' : '' }}">
                {{ number_format((float) ($stats['total_variance'] ?? 0), 2) }}
            </dd>
        </div>
    </div>

    <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-[#e5e5e5]">
            <h3 class="text-lg font-semibold text-[#171717]">Currency Positions</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Currency</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Quantity</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Avg Cost</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Market Value (MYR)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Unrealized P/L</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    @forelse($positions as $position)
                    <tr class="hover:bg-[#f7f7f8]/50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-[#f7f7f8] rounded-lg flex items-center justify-center font-bold text-xs">
                                    {{ substr($position['currency_code'], 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-medium text-[#171717]">{{ $position['currency_code'] }}</p>
                                    <p class="text-xs text-[#6b6b6b]">{{ $position['currency_name'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-[#171717]">{{ number_format((float) $position['quantity'], 2) }}</td>
                        <td class="px-4 py-3 font-mono text-[#171717]">{{ number_format((float) $position['avg_cost'], 4) }}</td>
                        <td class="px-4 py-3 font-mono text-[#171717]">{{ number_format((float) $position['market_value'], 2) }} MYR</td>
                        <td class="px-4 py-3">
                            @php
                                $pl = (float) ($position['unrealized_pl'] ?? 0);
                                $plClass = $pl >= 0 ? 'text-green-600' : 'text-red-600';
                            @endphp
                            <span class="font-mono {{ $plClass }}">
                                {{ $pl >= 0 ? '+' : '' }}{{ number_format($pl, 2) }} MYR
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('stock-cash.position', $position['id']) }}" class="text-[#d4a843] hover:underline text-sm">Details</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-[#6b6b6b]">No positions found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
