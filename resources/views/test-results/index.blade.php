<x-app-layout title="Test Results">
    <x-page-header title="Test Results" description="Test run history and results" />

    @if (isset($statistics))
        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Total Runs" :value="$statistics['total_runs'] ?? 0" color="blue" />
            <x-stat-card label="Total Tests" :value="$statistics['total_tests'] ?? 0" color="purple" />
            <x-stat-card label="Avg Pass Rate" :value="($statistics['overall_pass_rate'] ?? 0).'%'" color="green" />
            <x-stat-card label="Avg Duration" :value="($statistics['avg_duration'] ?? 0).'s'" color="yellow" />
        </div>
    @endif

    <x-card class="mt-4 !p-0">
        @if ($testRuns->isEmpty())
            <x-empty-state title="No test runs found" description="No test results have been recorded yet." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Run</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Suite</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Total</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Passed</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Failed</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Pass Rate</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Date</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($testRuns as $run)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 font-medium">Run #{{ $run->id }}</td>
                                <td class="px-4 py-3">{{ $run->test_suite }}</td>
                                <td class="px-4 py-3 text-right">{{ $run->total_tests }}</td>
                                <td class="px-4 py-3 text-right text-success-text">{{ $run->passed_tests }}</td>
                                <td class="px-4 py-3 text-right text-danger">{{ $run->failed_tests }}</td>
                                <td class="px-4 py-3 text-right">
                                    @php
                                        $passRate = $run->total_tests > 0 ? round(($run->passed / $run->total_tests) * 100) : 0;
                                        $badgeVariant = $passRate >= 80 ? 'success' : ($passRate >= 50 ? 'warning' : 'error');
                                    @endphp
                                    <x-badge :variant="$badgeVariant">{{ $passRate }}%</x-badge>
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="$run->status->value === 'completed' ? 'success' : ($run->status->value === 'failed' ? 'error' : 'warning')">{{ ucfirst($run->status->value) }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-ink-muted">{{ $run->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('test-results.show', $run) }}" class="text-info hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-4 py-3">
                {{ $testRuns->links() }}
            </div>
        @endif
    </x-card>
</x-app-layout>

