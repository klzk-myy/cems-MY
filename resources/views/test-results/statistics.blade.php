<x-app-layout>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Test Statistics</h1>
                <p class="mt-1 text-sm text-gray-500">Last {{ $days }} days performance overview</p>
            </div>
            <a href="{{ route('test-results.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                Back to Test Runs
            </a>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Total Runs</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($statistics['total_runs']) }}</p>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Total Tests</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($statistics['total_tests']) }}</p>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Overall Pass Rate</p>
                <p class="mt-1 text-2xl font-semibold text-green-600">{{ $statistics['overall_pass_rate'] }}%</p>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Avg Duration</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($statistics['avg_duration'], 2) }}s</p>
            </div>
        </div>

        <!-- Pass Rate Trend Chart Placeholder -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-semibold text-gray-900">Pass Rate Trend (Last {{ $days }} Days)</h2>
            </div>
            <div class="p-6">
                @if(!empty($trendData) && count($trendData) > 0)
                    <div class="h-64 flex items-end justify-between gap-2">
                        @foreach($trendData as $dataPoint)
                            <div class="flex-1 flex flex-col items-center">
                                <div class="w-full bg-gray-200 rounded-t relative" style="height: {{ max($dataPoint['pass_rate'], 5) }}%">
                                    @if($dataPoint['pass_rate'] >= 80)
                                        <div class="absolute inset-0 bg-green-500 rounded-t"></div>
                                    @elseif($dataPoint['pass_rate'] >= 50)
                                        <div class="absolute inset-0 bg-yellow-500 rounded-t"></div>
                                    @else
                                        <div class="absolute inset-0 bg-red-500 rounded-t"></div>
                                    @endif
                                </div>
                                <span class="mt-2 text-xs text-gray-500">{{ $dataPoint['date'] }}</span>
                                <span class="text-xs font-medium text-gray-700">{{ $dataPoint['pass_rate'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="h-64 flex items-center justify-center bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">No trend data available for the selected period</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Latest Results by Suite -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-semibold text-gray-900">Latest Results by Suite</h2>
            </div>
            <table class="min-w-full divide-y divide-[#e5e5e5]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Suite</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Run</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pass Rate</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trend</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-[#e5e5e5]">
                    @forelse($latestBySuite as $suiteName => $suiteData)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $suiteName }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $suiteData['last_run']->created_at->format('M d, H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($suiteData['last_run']->status === 'passed')
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Passed</span>
                                @elseif($suiteData['last_run']->status === 'failed')
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Failed</span>
                                @elseif($suiteData['last_run']->status === 'error')
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Error</span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">{{ ucfirst($suiteData['last_run']->status) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">{{ $suiteData['last_run']->tests_passed ?? 0 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">{{ $suiteData['last_run']->tests_failed ?? 0 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $suiteData['pass_rate'] >= 80 ? 'text-green-600' : ($suiteData['pass_rate'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $suiteData['pass_rate'] }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($suiteData['trend'] === 'up')
                                    <span class="inline-flex items-center text-green-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                        </svg>
                                    </span>
                                @elseif($suiteData['trend'] === 'down')
                                    <span class="inline-flex items-center text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                        </svg>
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-gray-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"></path>
                                        </svg>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">No suite data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Statistics Breakdown -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Status Distribution -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Status Distribution</h3>
                <div class="space-y-3">
                    @if(!empty($statistics['by_status']))
                        @foreach($statistics['by_status'] as $status => $count)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($status === 'passed')
                                        <span class="w-3 h-3 rounded-full bg-green-500"></span>
                                        <span class="text-sm text-gray-700">Passed</span>
                                    @elseif($status === 'failed')
                                        <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                        <span class="text-sm text-gray-700">Failed</span>
                                    @elseif($status === 'error')
                                        <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                                        <span class="text-sm text-gray-700">Error</span>
                                    @else
                                        <span class="w-3 h-3 rounded-full bg-gray-500"></span>
                                        <span class="text-sm text-gray-700">{{ ucfirst($status) }}</span>
                                    @endif
                                </div>
                                <span class="text-sm font-medium text-gray-900">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-500">No status data available</p>
                    @endif
                </div>
            </div>

            <!-- Pass Rate by Day -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Summary</h3>
                <div class="space-y-3">
                    @if(!empty($statistics['daily_summary']))
                        @foreach($statistics['daily_summary'] as $day)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <span class="text-sm text-gray-600">{{ $day['date'] }}</span>
                                <div class="flex items-center gap-4">
                                    <span class="text-xs text-green-600">{{ $day['passed'] }} passed</span>
                                    <span class="text-xs text-red-600">{{ $day['failed'] }} failed</span>
                                    <span class="text-sm font-medium {{ $day['pass_rate'] >= 80 ? 'text-green-600' : ($day['pass_rate'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $day['pass_rate'] }}%
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-500">No daily summary available</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
