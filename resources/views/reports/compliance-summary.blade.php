<x-app-layout title="Compliance Summary Report">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Compliance Summary Report</h1>
                <p class="text-sm text-gray-500 mt-1">AML/CFT compliance overview and flagged transactions</p>
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span>{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Large Transactions (>= RM 50k)</div>
                <div class="text-2xl font-semibold text-gray-900">{{ number_format($largeTransactions) }}</div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">EDD Cases</div>
                <div class="text-2xl font-semibold text-blue-600">{{ number_format($eddCount) }}</div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Suspicious Transactions</div>
                <div class="text-2xl font-semibold text-red-600">{{ number_format($suspiciousCount) }}</div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Total Flagged</div>
                <div class="text-2xl font-semibold text-gray-900">{{ number_format($flaggedStats['total']) }}</div>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.compliance-summary') }}" class="flex flex-wrap gap-4 items-end">
                <div class="flex flex-col gap-2">
                    <label for="start_date" class="text-xs font-medium text-gray-500 uppercase tracking-wide">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate }}"
                        class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div class="flex flex-col gap-2">
                    <label for="end_date" class="text-xs font-medium text-gray-500 uppercase tracking-wide">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                        class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Update Report
                </button>
            </form>
        </div>

        <!-- Flagged by Type Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Flagged Transactions by Type</h2>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Flag Type</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Count</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($flaggedStats['by_type'] as $type => $count)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $type }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($count) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 text-right">
                                    {{ $flaggedStats['total'] > 0 ? round($count / $flaggedStats['total'] * 100, 1) : 0 }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-gray-500">No flagged transactions</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Compliance Metrics -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Compliance Metrics</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Large Transactions (>= RM 50k)</span>
                        <span class="text-sm font-medium text-gray-900">{{ number_format($largeTransactions) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">EDD Cases Processed</span>
                        <span class="text-sm font-medium text-blue-600">{{ number_format($eddCount) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Suspicious Transaction Reports</span>
                        <span class="text-sm font-medium text-red-600">{{ number_format($suspiciousCount) }}</span>
                    </div>
                    <div class="pt-4 border-t border-[#e5e5e5]">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Total Compliance Flags</span>
                            <span class="text-lg font-semibold text-gray-900">{{ number_format($flaggedStats['total']) }}</span>
                        </div>
                    </div>
                    @if($largeTransactions > 0)
                        <div class="pt-4 border-t border-[#e5e5e5]">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">STR Filing Rate</span>
                                <span class="text-sm font-medium {{ $suspiciousCount > 0 ? 'text-yellow-600' : 'text-green-600' }}">
                                    {{ $largeTransactions > 0 ? round($suspiciousCount / $largeTransactions * 100, 1) : 0 }}%
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- CTR Threshold Transactions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-gray-900">Large Cash Transaction Summary (>= RM 25,000)</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Transactions</div>
                        <div class="text-2xl font-semibold text-gray-900">{{ number_format($largeTransactions) }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">CTOS Threshold (RM 25,000)</div>
                        <div class="text-2xl font-semibold text-gray-900">{{ number_format($flaggedStats['ctos_threshold'] ?? 0) }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Compliance Hold</div>
                        <div class="text-2xl font-semibold text-yellow-600">{{ number_format($flaggedStats['pending_compliance'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>