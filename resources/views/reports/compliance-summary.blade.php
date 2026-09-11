<x-app-layout title="Compliance Summary Report">
    <x-page-header title="Compliance Summary Report" description="Compliance activity summary for {{ $startDate }} to {{ $endDate }}" />

    @if (isset($flaggedStats))
        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Total Flagged" :value="$flaggedStats['total'] ?? 0" color="blue" />
            <x-stat-card label="EDD Required" :value="$eddCount ?? 0" color="yellow" />
            <x-stat-card label="Suspicious Activity" :value="$suspiciousCount ?? 0" color="red" />
        </div>
    @endif

    <x-card class="mt-4 !p-0">
        @if (!empty($flaggedStats['by_type']))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Flag Type</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($flaggedStats['by_type'] as $type => $count)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 font-medium">{{ str_replace('_', ' ', $type) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state title="No data available" description="No flagged transactions found for the selected period." />
        @endif
    </x-card>
</x-app-layout>

