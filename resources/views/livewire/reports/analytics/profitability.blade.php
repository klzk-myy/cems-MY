@extends('layouts.base')

@section('title', 'Currency Profitability Analysis')

@section('content')
<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Currency Profitability Analysis</h2>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600">From:</label>
                        <input type="date" wire:model.live="startDate" class="text-sm border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600">To:</label>
                        <input type="date" wire:model.live="endDate" class="text-sm border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6">
            @if(empty($positions))
                <div class="text-center py-8 text-gray-500">
                    No position data available.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Cost Rate</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Rate</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unrealized P&L</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Realized P&L</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total P&L</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($positions as $pos)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $pos['currency_code'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format((float)$pos['balance'], 4) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format((float)$pos['avg_cost_rate'], 4) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format((float)$pos['current_rate'], 4) }}</td>
                                    <td class="px-4 py-3 text-sm text-right {{ (float)$pos['unrealized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format((float)$pos['unrealized_pnl'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ (float)$pos['realized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format((float)$pos['realized_pnl'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-right {{ (float)$pos['total_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format((float)$pos['total_pnl'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900">Total</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-sm font-bold text-right {{ (float)$totals['total_unrealized'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format((float)$totals['total_unrealized'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-right {{ (float)$totals['total_realized'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format((float)$totals['total_realized'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-right {{ (float)$totals['total_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format((float)$totals['total_pnl'], 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
