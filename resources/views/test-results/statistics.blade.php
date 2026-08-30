<x-app-layout title="Test Statistics">
    <x-page-header title="Test Statistics" description="Test results" />

    <x-stat-grid>
        <x-stat-card label="Total Runs" value="{{ $statistics['total_runs'] ?? 0 }}" color="blue" />
        <x-stat-card label="Total Tests" value="{{ $statistics['total_tests'] ?? 0 }}" color="green" />
        <x-stat-card label="Pass Rate" value="{{ number_format($statistics['overall_pass_rate'] ?? 0, 1) }}%" color="purple" />
        <x-stat-card label="Avg Duration" value="{{ number_format($statistics['avg_duration'] ?? 0, 2) }}s" color="gray" />
    </x-stat-grid>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card title="Status Breakdown">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-status-dot color="success" />
                        <span class="text-sm text-ink">Passed</span>
                    </div>
                    <span class="text-sm font-medium text-ink">{{ $statistics['by_status']['passed'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-status-dot color="danger" />
                        <span class="text-sm text-ink">Failed</span>
                    </div>
                    <span class="text-sm font-medium text-ink">{{ $statistics['by_status']['failed'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-status-dot color="warning" />
                        <span class="text-sm text-ink">Error</span>
                    </div>
                    <span class="text-sm font-medium text-ink">{{ $statistics['by_status']['error'] ?? 0 }}</span>
                </div>
            </div>
        </x-card>

        <x-card title="Pass Rate Trend">
            @php
                // Ensure trend data is in the expected format (array of arrays with date/pass_rate keys)
                $trendDataArray = $trendData instanceof \Illuminate\Support\Collection
                    ? $trendData->toArray()
                    : (is_array($trendData) ? $trendData : []);
                $trendLabels = array_column($trendDataArray, 'date');
                $trendValues = array_column($trendDataArray, 'pass_rate');
            @endphp
            <x-chart-trend title="Pass Rate Trend" :labels="$trendLabels" :values="$trendValues" color="success" />
        </x-card>
    </div>
</x-app-layout>
