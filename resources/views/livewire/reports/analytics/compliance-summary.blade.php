<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Compliance Summary</h2>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Large Transactions (≥RM 50,000)</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($largeTransactions) }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">EDD Required</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($eddCount) }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Suspicious Activity</div>
                    <div class="text-2xl font-bold text-red-600 mt-1">{{ number_format($suspiciousCount) }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Flagged Transactions</div>
                    <div class="text-2xl font-bold text-yellow-600 mt-1">{{ number_format(array_sum(array_column($flaggedStats, 'count'))) }}</div>
                </div>
            </div>

            @if(!empty($flaggedStats))
                <div class="mt-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Flag Types Breakdown</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Flag Type</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Count</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($flaggedStats as $stat)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $stat['flag_type'] ?? 'Unknown' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600 text-right">{{ number_format($stat['count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
