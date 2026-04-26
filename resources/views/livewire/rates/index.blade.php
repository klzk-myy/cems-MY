<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Exchange Rates</h2>
                <div class="flex items-center gap-3">
                    @if($canSelectBranch)
                        <select wire:model="selectedBranchId" class="text-sm border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Branches</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch['id'] }}">{{ $branch['code'] }} - {{ $branch['name'] }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button wire:click="copyPrevious()" class="px-3 py-1.5 text-sm font-medium text-indigo-600 border border-indigo-600 rounded-lg hover:bg-indigo-50">
                        Copy Previous
                    </button>
                    <button wire:click="fetchFromApi()" class="px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                        Fetch From API
                    </button>
                </div>
            </div>
        </div>

        <div class="p-6">
            @if(empty($rates))
                <div class="text-center py-8 text-gray-500">
                    No rates available.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Buy Rate</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sell Rate</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Spread</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($rates as $rate)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        {{ $rate['currency_code'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-green-600 text-right font-mono">
                                        {{ number_format((float) $rate['rate_buy'], 4) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-red-600 text-right font-mono">
                                        {{ number_format((float) $rate['rate_sell'], 4) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 text-center">
                                        {{ number_format((float) ($rate['spread'] ?? 0), 4) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ $rate['fetched_at'] ? \Carbon\Carbon::parse($rate['fetched_at'])->format('d M Y H:i') : 'N/A' }}
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