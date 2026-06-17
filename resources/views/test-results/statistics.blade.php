<x-app-layout title="Test Results">
    <div class="space-y-6">
        <x-page-header title="Test Statistics" :actions="true">
            Last {{ $days }} days performance overview

            <x-slot:actions>
                <x-button href="{{ route('test-results.index') }}" variant="secondary">Back to Test Runs</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid cols="4">
            <x-stat-card label="Total Runs" :value="number_format($statistics['total_runs'])" />
            <x-stat-card label="Total Tests" :value="number_format($statistics['total_tests'])" />
            <x-stat-card label="Overall Pass Rate" :value="$statistics['overall_pass_rate']" suffix="%" color="green" />
            <x-stat-card label="Avg Duration" :value="number_format($statistics['avg_duration'], 2)" suffix="s" />
        </x-stat-grid>

        <x-card title="Pass Rate Trend (Last {{ $days }} Days)">
            @if(!empty($trendData) && count($trendData) > 0)
                <div class="h-64 flex items-end justify-between gap-2">
                    @foreach($trendData as $dataPoint)
                        <div class="flex-1 flex flex-col items-center">
                            <x-chart-bar
                                :value="max($dataPoint['pass_rate'], 5)"
                                :color="$dataPoint['pass_rate'] >= 80 ? 'green' : ($dataPoint['pass_rate'] >= 50 ? 'yellow' : 'red')"
                            />
                            <span class="mt-2 text-xs text-ink-muted">{{ $dataPoint['date'] }}</span>
                            <span class="text-xs font-medium text-ink-muted">{{ $dataPoint['pass_rate'] }}%</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="h-64 flex items-center justify-center bg-canvas-subtle rounded-lg">
                    <p class="text-sm text-ink-muted">No trend data available for the selected period</p>
                </div>
            @endif
        </x-card>

        <x-card title="Latest Results by Suite">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Suite</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Run</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Passed</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Failed</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Pass Rate</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Trend</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($latestBySuite as $suiteName => $suiteData)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">{{ $suiteName }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $suiteData['last_run']->created_at->format('M d, H:i') }}</td>
                            <td class="px-4 py-3">
                                @switch($suiteData['last_run']->status)
                                    @case(\App\Enums\TestResultStatus::Passed)
                                        <x-badge variant="success">Passed</x-badge>
                                        @break
                                    @case(\App\Enums\TestResultStatus::Failed)
                                        <x-badge variant="danger">Failed</x-badge>
                                        @break
                                    @case(\App\Enums\TestResultStatus::Error)
                                        <x-badge variant="warning">Error</x-badge>
                                        @break
                                    @default
                                        <x-badge variant="gray">{{ $suiteData['last_run']->status->label() }}</x-badge>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-success-text">{{ $suiteData['last_run']->tests_passed ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-danger-text">{{ $suiteData['last_run']->tests_failed ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm font-medium {{ $suiteData['pass_rate'] >= 80 ? 'text-success-text' : ($suiteData['pass_rate'] >= 50 ? 'text-warning-text' : 'text-danger-text') }}">
                                {{ $suiteData['pass_rate'] }}%
                            </td>
                            <td class="px-4 py-3">
                                @if($suiteData['trend'] === 'up')
                                    <span class="inline-flex items-center text-success-text">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                        </svg>
                                    </span>
                                @elseif($suiteData['trend'] === 'down')
                                    <span class="inline-flex items-center text-danger-text">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                        </svg>
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-ink-muted/50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"></path>
                                        </svg>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No suite data available" :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-card title="Status Distribution">
                <div class="space-y-3">
                    @if(!empty($statistics['by_status']))
                        @foreach($statistics['by_status'] as $status => $count)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($status === 'passed')
                                        <span class="w-3 h-3 rounded-full bg-success"></span>
                                        <span class="text-sm text-ink-muted">Passed</span>
                                    @elseif($status === 'failed')
                                        <span class="w-3 h-3 rounded-full bg-danger"></span>
                                        <span class="text-sm text-ink-muted">Failed</span>
                                    @elseif($status === 'error')
                                        <span class="w-3 h-3 rounded-full bg-warning"></span>
                                        <span class="text-sm text-ink-muted">Error</span>
                                    @else
                                        <span class="w-3 h-3 rounded-full bg-canvas-subtle"></span>
                                        <span class="text-sm text-ink-muted">{{ ucfirst($status) }}</span>
                                    @endif
                                </div>
                                <span class="text-sm font-medium text-ink">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-ink-muted">No status data available</p>
                    @endif
                </div>
            </x-card>

            <x-card title="Daily Summary">
                <div class="space-y-3">
                    @if(!empty($statistics['daily_summary']))
                        @foreach($statistics['daily_summary'] as $day)
                            <div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                                <span class="text-sm text-ink-muted">{{ $day['date'] }}</span>
                                <div class="flex items-center gap-4">
                                    <span class="text-xs text-success-text">{{ $day['passed'] }} passed</span>
                                    <span class="text-xs text-danger-text">{{ $day['failed'] }} failed</span>
                                    <span class="text-sm font-medium {{ $day['pass_rate'] >= 80 ? 'text-success-text' : ($day['pass_rate'] >= 50 ? 'text-warning-text' : 'text-danger-text') }}">
                                        {{ $day['pass_rate'] }}%
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-ink-muted">No daily summary available</p>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
