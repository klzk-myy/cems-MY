<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">LCTR Reports</h1>
            <a href="{{ route('reports.lctr.create') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">New Report</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Report ID</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Period</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Total Transactions</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Total Value</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $report->report_number }}</td>
                        <td class="px-4 py-3">{{ $report->period }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($report->total_transactions) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($report->total_value, 2) }}</td>
                        <td class="px-4 py-3">
                            @if($report->status === 'submitted')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Submitted</span>
                            @elseif($report->status === 'draft')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Draft</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">{{ $report->status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('reports.lctr.show', $report->id) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center">No reports found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $reports->links() }}
        </div>
    </div>
</div>