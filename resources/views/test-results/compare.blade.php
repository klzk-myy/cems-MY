<x-app-layout title="Test Results">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Compare Test Runs</h1>
                <p class="mt-1 text-sm text-ink-muted">Run #{{ $run1->id }} vs Run #{{ $run2->id }}</p>
            </div>
            <a href="{{ route('test-results.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-canvas-subtle">
                Back to List
            </a>
        </div>

        <!-- Side-by-Side Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Run 1 Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5] bg-canvas-subtle">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-ink">Run #{{ $run1->id }}</h2>
                        <span class="text-sm text-ink-muted">{{ $run1->created_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Status</span>
                        @if($run1->status === 'passed')
                            <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-green-100 text-green-700">Passed</span>
                        @elseif($run1->status === 'failed')
                            <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-red-100 text-red-700">Failed</span>
                        @elseif($run1->status === 'error')
                            <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-yellow-100 text-yellow-700">Error</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-canvas-subtle text-gray-700">{{ ucfirst($run1->status) }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Total Tests</span>
                        <span class="text-sm font-medium text-ink">{{ number_format($run1->tests_total ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Passed</span>
                        <span class="text-sm font-medium text-green-600">{{ number_format($run1->tests_passed ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Failed</span>
                        <span class="text-sm font-medium text-red-600">{{ number_format($run1->tests_failed ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Errors</span>
                        <span class="text-sm font-medium text-yellow-600">{{ number_format($run1->tests_errors ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Duration</span>
                        <span class="text-sm font-medium text-ink">{{ $run1->duration ? number_format($run1->duration, 2) . 's' : 'N/A' }}</span>
                    </div>
                    <div class="pt-4 border-t border-[#e5e5e5]">
                        <a href="{{ route('test-results.show', $run1->id) }}"
                           class="text-sm font-medium text-ink hover:text-gray-700">
                            View Full Details &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Run 2 Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5] bg-canvas-subtle">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-ink">Run #{{ $run2->id }}</h2>
                        <span class="text-sm text-ink-muted">{{ $run2->created_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Status</span>
                        @if($run2->status === 'passed')
                            <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-green-100 text-green-700">Passed</span>
                        @elseif($run2->status === 'failed')
                            <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-red-100 text-red-700">Failed</span>
                        @elseif($run2->status === 'error')
                            <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-yellow-100 text-yellow-700">Error</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-canvas-subtle text-gray-700">{{ ucfirst($run2->status) }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Total Tests</span>
                        <span class="text-sm font-medium text-ink">{{ number_format($run2->tests_total ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Passed</span>
                        <span class="text-sm font-medium text-green-600">{{ number_format($run2->tests_passed ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Failed</span>
                        <span class="text-sm font-medium text-red-600">{{ number_format($run2->tests_failed ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Errors</span>
                        <span class="text-sm font-medium text-yellow-600">{{ number_format($run2->tests_errors ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Duration</span>
                        <span class="text-sm font-medium text-ink">{{ $run2->duration ? number_format($run2->duration, 2) . 's' : 'N/A' }}</span>
                    </div>
                    <div class="pt-4 border-t border-[#e5e5e5]">
                        <a href="{{ route('test-results.show', $run2->id) }}"
                           class="text-sm font-medium text-ink hover:text-gray-700">
                            View Full Details &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison Summary -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-semibold text-ink">Differences</h2>
            </div>
            <table class="min-w-full divide-y divide-[#e5e5e5]">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Metric</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Run #{{ $run1->id }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Run #{{ $run2->id }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Change</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-[#e5e5e5]">
                    <!-- Total Tests -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Total Tests</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ number_format($run1->tests_total ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ number_format($run2->tests_total ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $totalDiff = ($run2->tests_total ?? 0) - ($run1->tests_total ?? 0); @endphp
                            @if($totalDiff > 0)
                                <span class="text-sm font-medium text-green-600">+{{ number_format($totalDiff) }}</span>
                            @elseif($totalDiff < 0)
                                <span class="text-sm font-medium text-red-600">{{ number_format($totalDiff) }}</span>
                            @else
                                <span class="text-sm text-ink-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Passed -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Passed</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">{{ number_format($run1->tests_passed ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">{{ number_format($run2->tests_passed ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $passedDiff = ($run2->tests_passed ?? 0) - ($run1->tests_passed ?? 0); @endphp
                            @if($passedDiff > 0)
                                <span class="text-sm font-medium text-green-600">+{{ number_format($passedDiff) }}</span>
                            @elseif($passedDiff < 0)
                                <span class="text-sm font-medium text-red-600">{{ number_format($passedDiff) }}</span>
                            @else
                                <span class="text-sm text-ink-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Failed -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Failed</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">{{ number_format($run1->tests_failed ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">{{ number_format($run2->tests_failed ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $failedDiff = ($run2->tests_failed ?? 0) - ($run1->tests_failed ?? 0); @endphp
                            @if($failedDiff > 0)
                                <span class="text-sm font-medium text-red-600">+{{ number_format($failedDiff) }}</span>
                            @elseif($failedDiff < 0)
                                <span class="text-sm font-medium text-green-600">{{ number_format($failedDiff) }}</span>
                            @else
                                <span class="text-sm text-ink-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Errors -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Errors</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 font-medium">{{ number_format($run1->tests_errors ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 font-medium">{{ number_format($run2->tests_errors ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $errorDiff = ($run2->tests_errors ?? 0) - ($run1->tests_errors ?? 0); @endphp
                            @if($errorDiff > 0)
                                <span class="text-sm font-medium text-red-600">+{{ number_format($errorDiff) }}</span>
                            @elseif($errorDiff < 0)
                                <span class="text-sm font-medium text-green-600">{{ number_format($errorDiff) }}</span>
                            @else
                                <span class="text-sm text-ink-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Duration -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Duration</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $run1->duration ? number_format($run1->duration, 2) . 's' : 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $run2->duration ? number_format($run2->duration, 2) . 's' : 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($run1->duration && $run2->duration)
                                @php $durationDiff = $run2->duration - $run1->duration; @endphp
                                @if($durationDiff > 0)
                                    <span class="text-sm font-medium text-red-600">+{{ number_format($durationDiff, 2) }}s</span>
                                @elseif($durationDiff < 0)
                                    <span class="text-sm font-medium text-green-600">{{ number_format($durationDiff, 2) }}s</span>
                                @else
                                    <span class="text-sm text-ink-muted">0s</span>
                                @endif
                            @else
                                <span class="text-sm text-ink-muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Pass Rate -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Pass Rate</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            @php $rate1 = $run1->tests_total > 0 ? round(($run1->tests_passed / $run1->tests_total) * 100, 1) : 0; @endphp
                            {{ $rate1 }}%
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            @php $rate2 = $run2->tests_total > 0 ? round(($run2->tests_passed / $run2->tests_total) * 100, 1) : 0; @endphp
                            {{ $rate2 }}%
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $rateDiff = round($rate2 - $rate1, 1); @endphp
                            @if($rateDiff > 0)
                                <span class="text-sm font-medium text-green-600">+{{ $rateDiff }}%</span>
                            @elseif($rateDiff < 0)
                                <span class="text-sm font-medium text-red-600">{{ $rateDiff }}%</span>
                            @else
                                <span class="text-sm text-ink-muted">0%</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Test Changes -->
        @php
            $run1Failures = collect($run1->failures ?? [])->pluck('test_name')->toArray();
            $run2Failures = collect($run2->failures ?? [])->pluck('test_name')->toArray();
            $newlyFailed = array_diff($run2Failures, $run1Failures);
            $fixedTests = array_diff($run1Failures, $run2Failures);
        @endphp

        @if(!empty($newlyFailed) || !empty($fixedTests))
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Newly Failed Tests -->
                @if(!empty($newlyFailed))
                    <div class="bg-white border border-red-200 rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-red-200 bg-red-50">
                            <h3 class="text-lg font-semibold text-red-700">Newly Failed Tests ({{ count($newlyFailed) }})</h3>
                        </div>
                        <ul class="divide-y divide-[#e5e5e5]">
                            @foreach($newlyFailed as $testName)
                                <li class="px-6 py-3 text-sm text-red-600">{{ $testName }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Fixed Tests -->
                @if(!empty($fixedTests))
                    <div class="bg-white border border-green-200 rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-green-200 bg-green-50">
                            <h3 class="text-lg font-semibold text-green-700">Fixed Tests ({{ count($fixedTests) }})</h3>
                        </div>
                        <ul class="divide-y divide-[#e5e5e5]">
                            @foreach($fixedTests as $testName)
                                <li class="px-6 py-3 text-sm text-green-600">{{ $testName }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <!-- Environment Comparison -->
        @if($run1->branch !== $run2->branch || $run1->commit_hash !== $run2->commit_hash)
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-lg font-semibold text-ink mb-4">Environment Differences</h3>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Branch (Run #{{ $run1->id }})</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $run1->branch ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Branch (Run #{{ $run2->id }})</dt>
                        <dd class="mt-1 text-sm text-ink {{ $run1->branch !== $run2->branch ? 'text-yellow-600 font-medium' : '' }}">{{ $run2->branch ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Commit (Run #{{ $run1->id }})</dt>
                        <dd class="mt-1 text-sm text-ink font-mono">{{ $run1->commit_hash ? substr($run1->commit_hash, 0, 8) : 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Commit (Run #{{ $run2->id }})</dt>
                        <dd class="mt-1 text-sm text-ink font-mono {{ $run1->commit_hash !== $run2->commit_hash ? 'text-yellow-600 font-medium' : '' }}">{{ $run2->commit_hash ? substr($run2->commit_hash, 0, 8) : 'N/A' }}</dd>
                    </div>
                </dl>
            </div>
        @endif
    </div>
</x-app-layout>
