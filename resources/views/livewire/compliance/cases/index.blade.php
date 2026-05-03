<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Compliance Cases</h1>
            <button wire:click="createCase" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">New Case</button>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Case ID</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Type</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Customer</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Priority</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $case->case_number }}</td>
                        <td class="px-4 py-3">{{ $case->type }}</td>
                        <td class="px-4 py-3">
                            @if($case->status === 'open')
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Open</span>
                            @elseif($case->status === 'investigating')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Investigating</span>
                            @else
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">{{ $case->status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $case->customer_name }}</td>
                        <td class="px-4 py-3">
                            @if($case->priority === 'high')
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">High</span>
                            @elseif($case->priority === 'medium')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Medium</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Low</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $case->created_at->format('Y-m-d') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center">No cases found</td>
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