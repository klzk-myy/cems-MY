<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Monthly Transaction Trends</h2>
                <div class="flex items-center gap-4">
                    <select wire:model.live="year" class="text-sm border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @for($y = now()->year; $y >= now()->year - 5; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                    <select wire:model.live="currency" class="text-sm border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="all">All Currencies</option>
                        @foreach($currencies as $curr)
                            <option value="{{ $curr }}">{{ $curr }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="p-6">
            @if(empty($monthlyData))
                <div class="text-center py-8 text-gray-500">
                    No transaction data available for the selected period.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Buy Volume (MYR)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sell Volume (MYR)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Volume (MYR)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Trend</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($monthlyData as $data)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        {{ \Carbon\Carbon::createFromDate($year, $data['month'], 1)->format('F') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format($data['count']) }}</td>
                                    <td class="px-4 py-3 text-sm text-green-600 text-right">{{ number_format((float)$data['buy_volume'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-red-600 text-right">{{ number_format((float)$data['sell_volume'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 text-right">{{ number_format((float)$data['total_volume'], 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if(isset($trends[$data['month']]) && $trends[$data['month']]['trend'] !== null)
                                            <span class="inline-flex items-center text-sm @if($trends[$data['month']]['direction'] === 'up') text-green-600 @elseif($trends[$data['month']]['direction'] === 'down') text-red-600 @else text-gray-500 @endif">
                                                @if($trends[$data['month']]['direction'] === 'up')
                                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                                                @elseif($trends[$data['month']]['direction'] === 'down')
                                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                @endif
                                                {{ number_format((float)$trends[$data['month']]['trend'], 2) }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-sm">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
