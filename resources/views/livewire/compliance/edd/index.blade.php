<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">EDD Cases</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Case ID</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Customer</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Risk Level</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Due Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $case->case_number }}</td>
                        <td class="px-4 py-3">{{ $case->customer_name }}</td>
                        <td class="px-4 py-3">
                            @if($case->risk_level === 'high')
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">High</span>
                            @elseif($case->risk_level === 'medium')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Medium</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Low</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $case->due_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            @if($case->status === 'completed')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Completed</span>
                            @elseif($case->status === 'in_progress')
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">In Progress</span>
                            @else
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Pending</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No cases found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $cases->links() }}
        </div>
    </div>
</div>