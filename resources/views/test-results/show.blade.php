<x-app-layout title="Test Results">
    <div class="space-y-6">
        <x-page-header title="Test Run #{{ $testResult->id }}" :actions="true">
            {{ $testResult->test_suite ?? 'No suite' }} &bull; {{ $testResult->created_at->format('M d, Y H:i:s') }}

            <x-slot:actions>
                @if($previousRun)
                    <x-button variant="secondary" href="{{ route('test-results.compare', [$testResult->id, $previousRun->id]) }}">
                        Compare with Previous
                    </x-button>
                @endif
                <x-button variant="secondary" href="{{ route('test-results.index') }}">
                    Back to List
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid cols="5">
            <x-stat-card label="Status">
                <x-badge :variant="match ($testResult->status->value) {
                    'passed' => 'success',
                    'failed' => 'danger',
                    'error' => 'warning',
                    default => 'gray',
                }">
                    {{ ucfirst($testResult->status->value) }}
                </x-badge>
            </x-stat-card>

            <x-stat-card label="Total Tests" :value="number_format($testResult->tests_total ?? 0)" />
            <x-stat-card label="Passed" :value="number_format($testResult->tests_passed ?? 0)" color="green" />
            <x-stat-card label="Failed" :value="number_format($testResult->tests_failed ?? 0)" color="red" />
            <x-stat-card label="Duration" :value="$testResult->duration ? number_format($testResult->duration, 2) . 's' : 'N/A'" />
        </x-stat-grid>

        @if($testResult->tests_failed > 0 && !empty($testResult->failures))
            <x-card title="Failed Tests ({{ count($testResult->failures) }})">
                @foreach($testResult->failures as $index => $failure)
                    <x-card-section>
                        <h3 class="text-sm font-medium text-ink">{{ $failure['test_name'] ?? 'Test ' . ($index + 1) }}</h3>

                        @if(!empty($failure['message']))
                            <p class="mt-2 text-sm text-danger-text">{{ $failure['message'] }}</p>
                        @endif

                        @if(!empty($failure['stack_trace']))
                            <pre class="mt-3 p-3 bg-surface-inverted text-canvas rounded-lg text-xs overflow-x-auto">{{ $failure['stack_trace'] }}</pre>
                        @endif
                    </x-card-section>
                @endforeach
            </x-card>
        @endif

        @if($testResult->tests_errors > 0 && !empty($testResult->errors))
            <x-card title="Errors ({{ count($testResult->errors) }})">
                @foreach($testResult->errors as $index => $error)
                    <x-card-section>
                        <h3 class="text-sm font-medium text-ink">{{ $error['test_name'] ?? 'Error ' . ($index + 1) }}</h3>

                        @if(!empty($error['message']))
                            <p class="mt-2 text-sm text-warning-text">{{ $error['message'] }}</p>
                        @endif

                        @if(!empty($error['stack_trace']))
                            <pre class="mt-3 p-3 bg-surface-inverted text-canvas rounded-lg text-xs overflow-x-auto">{{ $error['stack_trace'] }}</pre>
                        @endif
                    </x-card-section>
                @endforeach
            </x-card>
        @endif

        @if(!empty($testResult->output))
            <x-card title="Test Output">
                <x-card-section>
                    <pre class="p-4 bg-surface-inverted text-canvas rounded-lg text-xs overflow-x-auto">{{ $testResult->output }}</pre>
                </x-card-section>
            </x-card>
        @endif

        <x-card title="Run Information">
            <x-card-section>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Run ID</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $testResult->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Test Suite</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $testResult->test_suite ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Created At</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $testResult->created_at->format('M d, Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Branch</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $testResult->branch ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Commit</dt>
                        <dd class="mt-1 text-sm text-ink font-mono">{{ $testResult->commit_hash ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Runner</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $testResult->runner ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </x-card-section>
        </x-card>

        @if($previousRun)
            <x-card>
                <x-card-section>
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-ink">Previous Run</h3>
                            <p class="mt-1 text-sm text-ink-muted">
                                Run #{{ $previousRun->id }} &bull; {{ $previousRun->created_at->format('M d, Y H:i') }}
                                &bull; {{ $previousRun->tests_passed ?? 0 }}/{{ $previousRun->tests_total ?? 0 }} passed
                            </p>
                        </div>
                        <x-button variant="secondary" href="{{ route('test-results.show', $previousRun->id) }}">
                            View Previous Run
                        </x-button>
                    </div>
                </x-card-section>
            </x-card>
        @endif
    </div>
</x-app-layout>
