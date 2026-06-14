<x-app-layout title="Test Results">
    <div class="space-y-6">
        <x-page-header title="Compare Test Runs" :actions="true">
            Run #{{ $run1->id }} vs Run #{{ $run2->id }}

            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('test-results.index') }}">Back to List</x-button>
            </x-slot:actions>
        </x-page-header>

        <!-- Side-by-Side Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Run 1 Card -->
            <x-card>
                <div class="px-6 py-4 border-b border-border bg-canvas-subtle">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-ink">Run #{{ $run1->id }}</h2>
                        <span class="text-sm text-ink-muted">{{ $run1->created_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Status</span>
                        @if($run1->status === 'passed')
                            <x-badge variant="success">Passed</x-badge>
                        @elseif($run1->status === 'failed')
                            <x-badge variant="danger">Failed</x-badge>
                        @elseif($run1->status === 'error')
                            <x-badge variant="warning">Error</x-badge>
                        @else
                            <x-badge variant="gray">{{ ucfirst($run1->status) }}</x-badge>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Total Tests</span>
                        <span class="text-sm font-medium text-ink">{{ number_format($run1->tests_total ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Passed</span>
                        <span class="text-sm font-medium text-success-text">{{ number_format($run1->tests_passed ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Failed</span>
                        <span class="text-sm font-medium text-danger-text">{{ number_format($run1->tests_failed ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Errors</span>
                        <span class="text-sm font-medium text-warning-text">{{ number_format($run1->tests_errors ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Duration</span>
                        <span class="text-sm font-medium text-ink">{{ $run1->duration ? number_format($run1->duration, 2) . 's' : 'N/A' }}</span>
                    </div>
                    <div class="pt-4 border-t border-border">
                        <x-button variant="ghost" size="sm" href="{{ route('test-results.show', $run1->id) }}">View Full Details &rarr;</x-button>
                    </div>
                </div>
            </x-card>

            <!-- Run 2 Card -->
            <x-card>
                <div class="px-6 py-4 border-b border-border bg-canvas-subtle">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-ink">Run #{{ $run2->id }}</h2>
                        <span class="text-sm text-ink-muted">{{ $run2->created_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Status</span>
                        @if($run2->status === 'passed')
                            <x-badge variant="success">Passed</x-badge>
                        @elseif($run2->status === 'failed')
                            <x-badge variant="danger">Failed</x-badge>
                        @elseif($run2->status === 'error')
                            <x-badge variant="warning">Error</x-badge>
                        @else
                            <x-badge variant="gray">{{ ucfirst($run2->status) }}</x-badge>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Total Tests</span>
                        <span class="text-sm font-medium text-ink">{{ number_format($run2->tests_total ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Passed</span>
                        <span class="text-sm font-medium text-success-text">{{ number_format($run2->tests_passed ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Failed</span>
                        <span class="text-sm font-medium text-danger-text">{{ number_format($run2->tests_failed ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Errors</span>
                        <span class="text-sm font-medium text-warning-text">{{ number_format($run2->tests_errors ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Duration</span>
                        <span class="text-sm font-medium text-ink">{{ $run2->duration ? number_format($run2->duration, 2) . 's' : 'N/A' }}</span>
                    </div>
                    <div class="pt-4 border-t border-border">
                        <x-button variant="ghost" size="sm" href="{{ route('test-results.show', $run2->id) }}">View Full Details &rarr;</x-button>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Comparison Summary -->
        <x-card title="Differences">
            <x-table>
                <x-slot:thead>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Metric</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Run #{{ $run1->id }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Run #{{ $run2->id }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Change</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <!-- Total Tests -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Total Tests</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">{{ number_format($run1->tests_total ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">{{ number_format($run2->tests_total ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $totalDiff = ($run2->tests_total ?? 0) - ($run1->tests_total ?? 0); @endphp
                            @if($totalDiff > 0)
                                <span class="text-sm font-medium text-success-text">+{{ number_format($totalDiff) }}</span>
                            @elseif($totalDiff < 0)
                                <span class="text-sm font-medium text-danger-text">{{ number_format($totalDiff) }}</span>
                            @else
                                <span class="text-sm text-ink-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Passed -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Passed</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-success-text">{{ number_format($run1->tests_passed ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-success-text">{{ number_format($run2->tests_passed ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $passedDiff = ($run2->tests_passed ?? 0) - ($run1->tests_passed ?? 0); @endphp
                            @if($passedDiff > 0)
                                <span class="text-sm font-medium text-success-text">+{{ number_format($passedDiff) }}</span>
                            @elseif($passedDiff < 0)
                                <span class="text-sm font-medium text-danger-text">{{ number_format($passedDiff) }}</span>
                            @else
                                <span class="text-sm text-ink-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Failed -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Failed</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-danger-text">{{ number_format($run1->tests_failed ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-danger-text">{{ number_format($run2->tests_failed ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $failedDiff = ($run2->tests_failed ?? 0) - ($run1->tests_failed ?? 0); @endphp
                            @if($failedDiff > 0)
                                <span class="text-sm font-medium text-danger-text">+{{ number_format($failedDiff) }}</span>
                            @elseif($failedDiff < 0)
                                <span class="text-sm font-medium text-success-text">{{ number_format($failedDiff) }}</span>
                            @else
                                <span class="text-sm text-ink-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Errors -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Errors</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-warning-text">{{ number_format($run1->tests_errors ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-warning-text">{{ number_format($run2->tests_errors ?? 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $errorDiff = ($run2->tests_errors ?? 0) - ($run1->tests_errors ?? 0); @endphp
                            @if($errorDiff > 0)
                                <span class="text-sm font-medium text-danger-text">+{{ number_format($errorDiff) }}</span>
                            @elseif($errorDiff < 0)
                                <span class="text-sm font-medium text-success-text">{{ number_format($errorDiff) }}</span>
                            @else
                                <span class="text-sm text-ink-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    <!-- Duration -->
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink">Duration</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">{{ $run1->duration ? number_format($run1->duration, 2) . 's' : 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">{{ $run2->duration ? number_format($run2->duration, 2) . 's' : 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($run1->duration && $run2->duration)
                                @php $durationDiff = $run2->duration - $run1->duration; @endphp
                                @if($durationDiff > 0)
                                    <span class="text-sm font-medium text-danger-text">+{{ number_format($durationDiff, 2) }}s</span>
                                @elseif($durationDiff < 0)
                                    <span class="text-sm font-medium text-success-text">{{ number_format($durationDiff, 2) }}s</span>
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">
                            @php $rate1 = $run1->tests_total > 0 ? round(($run1->tests_passed / $run1->tests_total) * 100, 1) : 0; @endphp
                            {{ $rate1 }}%
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">
                            @php $rate2 = $run2->tests_total > 0 ? round(($run2->tests_passed / $run2->tests_total) * 100, 1) : 0; @endphp
                            {{ $rate2 }}%
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $rateDiff = round($rate2 - $rate1, 1); @endphp
                            @if($rateDiff > 0)
                                <span class="text-sm font-medium text-success-text">+{{ $rateDiff }}%</span>
                            @elseif($rateDiff < 0)
                                <span class="text-sm font-medium text-danger-text">{{ $rateDiff }}%</span>
                            @else
                                <span class="text-sm text-ink-muted">0%</span>
                            @endif
                        </td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <!-- Test Changes -->
        @php
            $run1Failures = collect($run1->failures ?? [])->pluck('test_name')->toArray();
            $run2Failures = collect($run2->failures ?? [])->pluck('test_name')->toArray();
            $newlyFailed = array_diff($run2Failures, $run1Failures);
            $fixedTests = array_diff($run1Failures, $run2Failures);
        @endphp

        @if(!empty($newlyFailed) || !empty($fixedTests))
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @if(!empty($newlyFailed))
                    <x-alert type="error" title="Newly Failed Tests ({{ count($newlyFailed) }})" class="mb-0">
                        <ul class="divide-y divide-border mt-2">
                            @foreach($newlyFailed as $testName)
                                <li class="py-2 text-sm text-danger-text">{{ $testName }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                @if(!empty($fixedTests))
                    <x-alert type="success" title="Fixed Tests ({{ count($fixedTests) }})" class="mb-0">
                        <ul class="divide-y divide-border mt-2">
                            @foreach($fixedTests as $testName)
                                <li class="py-2 text-sm text-success-text">{{ $testName }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif
            </div>
        @endif

        <!-- Environment Comparison -->
        @if($run1->branch !== $run2->branch || $run1->commit_hash !== $run2->commit_hash)
            <x-card title="Environment Differences">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 p-6">
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Branch (Run #{{ $run1->id }})</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $run1->branch ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Branch (Run #{{ $run2->id }})</dt>
                        <dd class="mt-1 text-sm text-ink {{ $run1->branch !== $run2->branch ? 'text-warning-text font-medium' : '' }}">{{ $run2->branch ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Commit (Run #{{ $run1->id }})</dt>
                        <dd class="mt-1 text-sm text-ink font-mono">{{ $run1->commit_hash ? substr($run1->commit_hash, 0, 8) : 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Commit (Run #{{ $run2->id }})</dt>
                        <dd class="mt-1 text-sm text-ink font-mono {{ $run1->commit_hash !== $run2->commit_hash ? 'text-warning-text font-medium' : '' }}">{{ $run2->commit_hash ? substr($run2->commit_hash, 0, 8) : 'N/A' }}</dd>
                    </div>
                </dl>
            </x-card>
        @endif
    </div>
</x-app-layout>
