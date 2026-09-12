<x-app-layout title="Risk Dashboard">
    <div class="space-y-6">
        <x-page-header
            title="Risk Dashboard"
            description="Customer risk overview and analytics"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.risk-dashboard.trends') }}">
                    View Trends
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @if (! $sanctionsLoaded)
            <x-alert type="warning" title="Sanctions lists not loaded">
                Sanction entries are empty - sanctions screening is ineffective until the lists are imported.
                Run <code>php artisan sanctions:update</code> immediately, or trigger an import from the
                <a href="{{ route('compliance.sanctions.index') }}" class="underline font-medium">sanctions lists screen</a>.
            </x-alert>
        @endif

        <x-stat-grid cols="4">
            <x-stat-card label="Critical Risk (Today)" :value="$summary['critical_risk']" color="red" />
            <x-stat-card label="High Risk (Today)" :value="$summary['high_risk']" color="yellow" />
            <x-stat-card label="Medium Risk (Today)" :value="$summary['medium_risk']" color="blue" />
            <x-stat-card label="Low Risk (Today)" :value="$summary['low_risk']" color="green" />
        </x-stat-grid>

        <x-stat-grid cols="3">
            <x-stat-card label="Customers Scored Today" :value="$summary['total_scored_today']" />
            <x-stat-card label="Deteriorating Trend" :value="$summary['deteriorating_trend']" color="red" />
            <x-stat-card label="Needs Re-screening" :value="$summary['needs_rescreening']" color="yellow" />
        </x-stat-grid>

        <x-filter-bar method="GET">
            <x-select
                name="threshold"
                :options="['40' => 'Score 40+', '60' => 'Score 60+ (High)', '80' => 'Score 80+ (Critical)']"
                placeholder=""
                :selected="(string) $threshold"
                inline
            />
            <x-button variant="primary" type="submit">Apply Threshold</x-button>
        </x-filter-bar>

        <x-card title="High-Risk Customers (score {{ $threshold }}+)">
            <div class="overflow-x-auto">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Risk Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Review</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse ($customers as $customer)
                            @php
                                $snapshot = $customer->latestRiskSnapshot;
                                $score = $snapshot?->overall_score ?? $customer->risk_score;
                            @endphp
                            <tr class="border-t border-border hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('compliance.risk-dashboard.customer', $customer) }}"
                                       class="text-primary hover:underline">
                                        {{ $customer->full_name }}
                                    </a>
                                    <div class="text-xs text-ink-muted">#{{ $customer->id }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm"><x-risk-badge :customer="$customer" /></td>
                                <td class="px-4 py-3 text-sm text-ink tabular-nums">{{ $score }}</td>
                                <td class="px-4 py-3 text-sm text-ink-muted whitespace-nowrap">
                                    {{ $snapshot?->snapshot_date?->format('d M Y') ?? $customer->risk_assessed_at?->format('d M Y') ?? 'Never' }}
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No customers at or above the selected risk threshold." :colspan="4" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </div>
        </x-card>

        {{ $customers->links() }}
    </div>
</x-app-layout>
