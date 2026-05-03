<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Audit Trail</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Timestamp</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">User</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Action</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Entity</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $log->user->name ?? 'System' }}</td>
                        <td class="px-4 py-3">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->entity_type }}</td>
                        <td class="px-4 py-3 text-sm">{{ $log->description }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No audit logs found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>