<x-app-layout title="Compliance Summary Report">
    <div class="p-6 space-y-6">
        <x-page-header title="Compliance Summary Report" description="AML/CFT compliance overview and flagged transactions" :actions="true">
            <x-slot:actions>
                <span class="text-sm text-ink-muted">
                    {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                </span>
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid cols="3">
            <x-stat-card label="EDD Cases" :value="number_format($eddCount)" color="blue" />
            <x-stat-card label="Suspicious Transactions" :value="number_format($suspiciousCount)" color="red" />
            <x-stat-card label="Total Flagged" :value="number_format($flaggedStats['total'])" />
        </x-stat-grid>

        <x-card>
            <form method="GET" action="{{ route('reports.compliance-summary') }}" class="flex flex-wrap gap-4 items-end">
                <x-input name="start_date" id="start_date" type="date" label="Start Date" :value="$startDate" inline />
                <x-input name="end_date" id="end_date" type="date" label="End Date" :value="$endDate" inline />
                <x-button variant="primary" type="submit">Update Report</x-button>
            </form>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Flagged Transactions by Type">
                <x-table>
                    <x-slot:thead>
                        <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wide">Flag Type</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wide">Count</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wide">Percentage</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($flaggedStats['by_type'] as $type => $count)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-6 py-4 text-sm text-ink">{{ $type }}</td>
                                <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($count) }}</td>
                                <td class="px-6 py-4 text-sm text-ink-muted text-right">
                                    {{ $flaggedStats['total'] > 0 ? round($count / $flaggedStats['total'] * 100, 1) : 0 }}%
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No flagged transactions" :colspan="3" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>

            <x-card title="Compliance Metrics">
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">EDD Cases Processed</span>
                        <span class="text-sm font-medium text-info">{{ number_format($eddCount) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Suspicious Transaction Reports</span>
                        <span class="text-sm font-medium text-danger-text">{{ number_format($suspiciousCount) }}</span>
                    </div>
                    <div class="pt-4 border-t border-border">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-ink-muted">Total Compliance Flags</span>
                            <span class="text-lg font-semibold text-ink">{{ number_format($flaggedStats['total']) }}</span>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
