<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Branch Openings</h1>
            <a href="{{ route('branch-openings.create') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">New Opening</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Branch</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Scheduled Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Progress</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($openings as $opening)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $opening->branch_name }}</td>
                        <td class="px-4 py-3">{{ $opening->scheduled_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            @if($opening->status === 'completed')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Completed</span>
                            @elseif($opening->status === 'in_progress')
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">In Progress</span>
                            @else
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">{{ $opening->status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $opening->progress }}%</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('branch-openings.show', $opening->id) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No branch openings found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>