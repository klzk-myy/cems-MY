<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Compliance Dashboard</h1>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-[var(--color-ink)]">{{ $stats['active_alerts'] }}</div>
                <div class="text-sm text-[var(--color-ink-muted)]">Active Alerts</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-[var(--color-ink)]">{{ $stats['open_cases'] }}</div>
                <div class="text-sm text-[var(--color-ink-muted)]">Open Cases</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-[var(--color-ink)]">{{ $stats['edd_pending'] }}</div>
                <div class="text-sm text-[var(--color-ink-muted)]">EDD Pending</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-[var(--color-ink)]">{{ $stats['str_pending'] }}</div>
                <div class="text-sm text-[var(--color-ink-muted)]">STR Draft</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-[var(--color-ink)] mb-4">Recent Alerts</h2>
            @if(!empty($recent_alerts))
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Priority</th>
                            <th class="text-left py-2">Customer</th>
                            <th class="text-left py-2">Status</th>
                            <th class="text-left py-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent_alerts as $alert)
                        <tr class="border-b">
                            <td class="py-2 capitalize">{{ $alert['priority'] }}</td>
                            <td class="py-2">{{ $alert['customer']['full_name'] ?? 'N/A' }}</td>
                            <td class="py-2 capitalize">{{ $alert['status'] }}</td>
                            <td class="py-2">{{ $alert['created_at'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-[var(--color-ink-muted)]">No recent alerts</p>
            @endif
        </div>
    </div>
</div>
