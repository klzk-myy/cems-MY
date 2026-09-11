<x-app-layout title="Position Limit Report">
    <x-page-header title="Position Limit Report" description="Currency position limit monitoring report" />

    @if (isset($reportData['summary']))
        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-3">
            <x-stat-card label="Total Currencies" :value="$reportData['summary']['total_currencies'] ?? 0" color="blue" />
            <x-stat-card label="Within Limit" :value="$reportData['summary']['within_limit'] ?? 0" color="green" />
            <x-stat-card label="Exceeding Limit" :value="$reportData['summary']['exceeding_limit'] ?? 0" color="red" />
        </div>
    @endif

    <x-card class="mt-4 !p-0">
        @if (!empty($reportData['positions']))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Currency</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Position</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Limit</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Utilization</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($reportData['positions'] as $position)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 font-medium">{{ $position['currency'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($position['position'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($position['limit'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($position['utilization'] ?? 0), 2) }}%</td>
                                <td class="px-4 py-3">
                                    @if (($position['exceeding'] ?? false))
                                        <x-badge variant="error">Exceeding</x-badge>
                                    @else
                                        <x-badge variant="success">Within Limit</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state title="No data available" description="No position data found." />
        @endif
    </x-card>

    @if ($reportGenerated)
        <x-card class="mt-4">
            <div class="flex items-center gap-2 text-sm text-ink-muted">
                <span class="inline-flex items-center rounded-full bg-success-subtle px-2.5 py-0.5 text-xs font-medium text-success-text">Generated</span>
                <span>Report generated on {{ $reportGenerated->created_at->format('Y-m-d H:i') }}</span>
            </div>
        </x-card>
    @endif
</x-app-layout>

