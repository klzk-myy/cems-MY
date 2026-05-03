<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Compliance Alerts</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Alert ID</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Type</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Description</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Severity</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($alerts as $alert)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $alert->alert_number }}</td>
                        <td class="px-4 py-3">{{ $alert->type }}</td>
                        <td class="px-4 py-3 text-sm">{{ Str::limit($alert->description, 50) }}</td>
                        <td class="px-4 py-3">
                            @if($alert->severity === 'critical')
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">Critical</span>
                            @elseif($alert->severity === 'high')
                                <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded text-xs">High</span>
                            @elseif($alert->severity === 'medium')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Medium</span>
                            @else
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Low</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($alert->status === 'resolved')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Resolved</span>
                            @elseif($alert->status === 'dismissed')
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Dismissed</span>
                            @else
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $alert->created_at->format('Y-m-d') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center">No alerts found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $alerts->links() }}
        </div>
    </div>
</div>