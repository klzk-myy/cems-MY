<x-app-layout title="Test Results">
    <div class="space-y-6">
        <x-page-header title="Test Results" :actions="true">
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('test-results.statistics') }}">View Statistics</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid cols="4">
            <x-stat-card label="Total Runs (30d)" :value="number_format($statistics['total_runs'])" color="blue" />
            <x-stat-card label="Passed" :value="number_format($statistics['passed'])" color="green" />
            <x-stat-card label="Failed" :value="number_format($statistics['failed'])" color="red" />
            <x-stat-card label="Avg Pass Rate" :value="$statistics['pass_rate'] . '%'" color="purple" />
        </x-stat-grid>

        <x-filter-bar>
            <x-select name="status" :options="['' => 'All Statuses', 'passed' => 'Passed', 'failed' => 'Failed', 'error' => 'Error']" :selected="request('status')" inline />
            <x-select name="suite" :options="collect($suites)->mapWithKeys(fn ($suite) => [$suite => $suite])->prepend('All Suites', '')->toArray()" :selected="request('suite')" inline />
            <x-button variant="primary" type="submit">Filter</x-button>

            @if(request()->has('status') || request()->has('suite'))
                <x-button variant="secondary" href="{{ route('test-results.index') }}">Clear Filters</x-button>
            @endif
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Run ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Suite</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Passed</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Failed</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($testRuns as $run)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">#{{ $run->id }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $run->test_suite ?? 'N/A' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($run->status === 'passed')
                                    <x-badge variant="success">Passed</x-badge>
                                @elseif($run->status === 'failed')
                                    <x-badge variant="danger">Failed</x-badge>
                                @elseif($run->status === 'error')
                                    <x-badge variant="warning">Error</x-badge>
                                @else
                                    <x-badge variant="gray">{{ ucfirst($run->status->value) }}</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-success-text">{{ $run->tests_passed ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-danger-text">{{ $run->tests_failed ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $run->duration ? number_format($run->duration, 2) . 's' : 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $run->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-button variant="ghost" size="sm" href="{{ route('test-results.show', $run->id) }}">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No test runs found" :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>

            @if($testRuns->hasPages())
                <div class="px-6 py-4 border-t border-border">
                    {{ $testRuns->withQueryString()->links() }}
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
