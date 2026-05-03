<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Audit Dashboard</h1>

        <div class="grid grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Total Logs Today</p>
                <p class="text-3xl font-bold mt-1">{{ $todayCount }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">User Actions</p>
                <p class="text-3xl font-bold mt-1">{{ $userActionsCount }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">System Events</p>
                <p class="text-3xl font-bold mt-1">{{ $systemEventsCount }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Warnings</p>
                <p class="text-3xl font-bold mt-1 text-red-600">{{ $warningsCount }}</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Recent Activity</h2>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Time</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">User</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Event</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Severity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentActivity as $activity)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 text-sm">{{ $activity->created_at->format('H:i') }}</td>
                        <td class="px-4 py-2">{{ $activity->user->name ?? 'System' }}</td>
                        <td class="px-4 py-2">{{ $activity->event }}</td>
                        <td class="px-4 py-2">
                            @if($activity->severity === 'critical')
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">Critical</span>
                            @elseif($activity->severity === 'warning')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Warning</span>
                            @else
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Info</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-center">No recent activity</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>