<x-app-layout title="Test Results">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-900">Test Results</h1>
            <a href="{{ route('test-results.statistics') }}"
               class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                View Statistics
            </a>
        </div>

        <!-- Statistics Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Runs (30d)</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($statistics['total_runs']) }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-blue-100">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Passed</p>
                        <p class="mt-1 text-2xl font-semibold text-green-600">{{ number_format($statistics['passed']) }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-green-100">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Failed</p>
                        <p class="mt-1 text-2xl font-semibold text-red-600">{{ number_format($statistics['failed']) }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-red-100">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Pass Rate</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $statistics['pass_rate'] }}%</p>
                    </div>
                    <div class="p-3 rounded-lg bg-purple-100">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
            <form method="GET" action="{{ route('test-results.index') }}" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status"
                            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900">
                        <option value="">All Statuses</option>
                        <option value="passed" {{ request('status') === 'passed' ? 'selected' : '' }}>Passed</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="error" {{ request('status') === 'error' ? 'selected' : '' }}>Error</option>
                    </select>
                </div>

                <div class="flex-1 min-w-[200px]">
                    <label for="suite" class="block text-sm font-medium text-gray-700 mb-1">Test Suite</label>
                    <select name="suite" id="suite"
                            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900">
                        <option value="">All Suites</option>
                        @foreach($suites as $suite)
                            <option value="{{ $suite }}" {{ request('suite') === $suite ? 'selected' : '' }}>{{ $suite }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Filter
                    </button>
                </div>

                @if(request()->has('status') || request()->has('suite'))
                    <div>
                        <a href="{{ route('test-results.index') }}"
                           class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                            Clear Filters
                        </a>
                    </div>
                @endif
            </form>
        </div>

        <!-- Test Runs Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-[#e5e5e5]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Run ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Suite</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-[#e5e5e5]">
                    @forelse($testRuns as $run)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#{{ $run->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $run->test_suite ?? 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($run->status === 'passed')
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Passed</span>
                                @elseif($run->status === 'failed')
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Failed</span>
                                @elseif($run->status === 'error')
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Error</span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">{{ ucfirst($run->status) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">{{ $run->tests_passed ?? 0 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">{{ $run->tests_failed ?? 0 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $run->duration ? number_format($run->duration, 2) . 's' : 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $run->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('test-results.show', $run->id) }}"
                                   class="text-gray-900 hover:text-gray-700 font-medium">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">No test runs found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            @if($testRuns->hasPages())
                <div class="px-6 py-4 border-t border-[#e5e5e5]">
                    {{ $testRuns->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
