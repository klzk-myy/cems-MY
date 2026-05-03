<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Compliance Risk Dashboard</h1>

        <div class="grid grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Open Cases</p>
                <p class="text-3xl font-bold mt-1">{{ $openCases }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">High Risk Customers</p>
                <p class="text-3xl font-bold mt-1 text-red-600">{{ $highRiskCustomers }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Pending Reviews</p>
                <p class="text-3xl font-bold mt-1 text-yellow-600">{{ $pendingReviews }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Compliance Score</p>
                <p class="text-3xl font-bold mt-1 text-green-600">{{ $complianceScore }}%</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-medium text-[var(--color-ink)] mb-4">Recent High Risk Alerts</h3>
                <table class="w-full">
                    <tbody>
                        @forelse($highRiskAlerts as $alert)
                        <tr class="border-b border-[var(--color-border)] py-2">
                            <td class="text-sm">{{ $alert->description }}</td>
                            <td class="text-sm text-right text-gray-500">{{ $alert->created_at->format('H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td class="text-center py-2">No recent alerts</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-medium text-[var(--color-ink)] mb-4">Compliance by Branch</h3>
                <table class="w-full">
                    <tbody>
                        @forelse($branchCompliance as $branch)
                        <tr class="border-b border-[var(--color-border)] py-2">
                            <td class="text-sm">{{ $branch['name'] }}</td>
                            <td class="text-sm text-right">
                                <span class="{{ $branch['score'] >= 80 ? 'text-green-600' : ($branch['score'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ $branch['score'] }}%
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td class="text-center py-2">No data</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>