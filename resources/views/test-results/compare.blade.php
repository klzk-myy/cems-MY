<x-app-layout title="Compare Test Runs">
    <x-page-header title="Run #{{ $run1->id }} vs Run #{{ $run2->id }}" description="Comparing {{ $run1->test_suite }} suite results" />

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card title="Run #{{ $run1->id }}">
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Total Tests</dt>
                    <dd class="text-sm font-medium">{{ $run1->total_tests }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Passed</dt>
                    <dd class="text-sm font-medium text-success-text">{{ $run1->passed }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Failed</dt>
                    <dd class="text-sm font-medium text-danger">{{ $run1->failed }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Pass Rate</dt>
                    <dd class="text-sm font-medium">{{ $run1->total_tests > 0 ? round(($run1->passed / $run1->total_tests) * 100) : 0 }}%</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Status</dt>
                    <dd class="text-sm font-medium">
                        <x-badge :variant="$run1->status->value === 'completed' || $run1->status->value === 'passed' ? 'success' : ($run1->status->value === 'failed' ? 'error' : 'warning')">{{ ucfirst($run1->status->value) }}</x-badge>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Date</dt>
                    <dd class="text-sm font-medium">{{ $run1->created_at->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card title="Run #{{ $run2->id }}">
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Total Tests</dt>
                    <dd class="text-sm font-medium">{{ $run2->total_tests }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Passed</dt>
                    <dd class="text-sm font-medium text-success-text">{{ $run2->passed }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Failed</dt>
                    <dd class="text-sm font-medium text-danger">{{ $run2->failed }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Pass Rate</dt>
                    <dd class="text-sm font-medium">{{ $run2->total_tests > 0 ? round(($run2->passed / $run2->total_tests) * 100) : 0 }}%</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Status</dt>
                    <dd class="text-sm font-medium">
                        <x-badge :variant="$run2->status->value === 'completed' || $run2->status->value === 'passed' ? 'success' : ($run2->status->value === 'failed' ? 'error' : 'warning')">{{ ucfirst($run2->status->value) }}</x-badge>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-ink-muted">Date</dt>
                    <dd class="text-sm font-medium">{{ $run2->created_at->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>
        </x-card>
    </div>
</x-app-layout>

