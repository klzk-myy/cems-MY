<x-app-layout title="Run #{{ $testResult->id }}">
    <x-page-header title="Run #{{ $testResult->id }}" description="Test run details for {{ $testResult->test_suite }} suite" />

    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Total Tests" :value="$testResult->total_tests" color="blue" />
        <x-stat-card label="Passed" :value="$testResult->passed" color="green" />
        <x-stat-card label="Failed" :value="$testResult->failed" color="red" />
        <x-stat-card label="Duration" :value="round($testResult->duration, 2).'s'" color="yellow" />
    </div>

    <x-card class="mt-4">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-ink-muted">Suite</dt>
                <dd class="mt-1 text-sm">{{ $testResult->test_suite }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-ink-muted">Status</dt>
                <dd class="mt-1 text-sm">
                    <x-badge :variant="$testResult->status->value === 'completed' || $testResult->status->value === 'passed' ? 'success' : ($testResult->status->value === 'failed' ? 'error' : 'warning')">{{ ucfirst($testResult->status->value) }}</x-badge>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-ink-muted">Git Branch</dt>
                <dd class="mt-1 text-sm">{{ $testResult->git_branch ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-ink-muted">Git Commit</dt>
                <dd class="mt-1 text-sm font-mono">{{ $testResult->git_commit ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-ink-muted">Executed By</dt>
                <dd class="mt-1 text-sm">{{ $testResult->executed_by ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-ink-muted">Run ID</dt>
                <dd class="mt-1 text-sm font-mono">{{ $testResult->run_id }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-ink-muted">Started At</dt>
                <dd class="mt-1 text-sm">{{ $testResult->started_at ? $testResult->started_at->format('Y-m-d H:i:s') : '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-ink-muted">Completed At</dt>
                <dd class="mt-1 text-sm">{{ $testResult->completed_at ? $testResult->completed_at->format('Y-m-d H:i:s') : '—' }}</dd>
            </div>
        </dl>
    </x-card>

    @if ($testResult->output)
        <x-card class="mt-4" title="Output">
            <pre class="overflow-x-auto rounded-lg bg-canvas-subtle p-4 text-xs">{{ $testResult->output }}</pre>
        </x-card>
    @endif

    @if (!empty($testResult->failures))
        <x-card class="mt-4" title="Failures">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Test</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($testResult->failures as $failure)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $failure['test'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-danger">{{ $failure['message'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</x-app-layout>

