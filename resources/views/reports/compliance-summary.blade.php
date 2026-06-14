<x-app-layout title="Compliance Summary Report">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Compliance Summary Report</h1>
                <p class="text-sm text-ink-muted mt-1">AML/CFT compliance overview and flagged transactions</p>
            </div>
            <div class="flex items-center gap-4 text-sm text-ink-muted">
                <span>{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-surface border border-border rounded-xl p-6">
                <div class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-2">EDD Cases</div>
                <div class="text-2xl font-semibold text-blue-600">{{ number_format($eddCount) }}</div>
            </div>
            <div class="bg-surface border border-border rounded-xl p-6">
                <div class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-2">Suspicious Transactions</div>
                <div class="text-2xl font-semibold text-red-600">{{ number_format($suspiciousCount) }}</div>
            </div>
            <div class="bg-surface border border-border rounded-xl p-6">
                <div class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-2">Total Flagged</div>
                <div class="text-2xl font-semibold text-ink">{{ number_format($flaggedStats['total']) }}</div>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.compliance-summary') }}" class="flex flex-wrap gap-4 items-end">
                <div class="flex flex-col gap-2">
                    <label for="start_date" class="text-xs font-medium text-ink-muted uppercase tracking-wide">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate }}"
                        class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                </div>
                <div class="flex flex-col gap-2">
                    <label for="end_date" class="text-xs font-medium text-ink-muted uppercase tracking-wide">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                        class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2.5 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Update Report
                </button>
            </form>
        </div>

        <!-- Flagged by Type Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-surface border border-border rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-medium text-ink">Flagged Transactions by Type</h2>
                </div>
                <table class="w-full">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wide">Flag Type</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wide">Count</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wide">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($flaggedStats['by_type'] as $type => $count)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-6 py-4 text-sm text-ink">{{ $type }}</td>
                                <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($count) }}</td>
                                <td class="px-6 py-4 text-sm text-ink-muted text-right">
                                    {{ $flaggedStats['total'] > 0 ? round($count / $flaggedStats['total'] * 100, 1) : 0 }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-ink-muted">No flagged transactions</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Compliance Metrics -->
            <div class="bg-surface border border-border rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-medium text-ink">Compliance Metrics</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">EDD Cases Processed</span>
                        <span class="text-sm font-medium text-blue-600">{{ number_format($eddCount) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Suspicious Transaction Reports</span>
                        <span class="text-sm font-medium text-red-600">{{ number_format($suspiciousCount) }}</span>
                    </div>
                    <div class="pt-4 border-t border-border">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Total Compliance Flags</span>
                            <span class="text-lg font-semibold text-ink">{{ number_format($flaggedStats['total']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>