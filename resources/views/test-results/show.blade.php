<x-app-layout>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Test Run #{{ $testResult->id }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $testResult->test_suite ?? 'No suite' }} &bull; {{ $testResult->created_at->format('M d, Y H:i:s') }}
                </p>
            </div>
            <div class="flex gap-3">
                @if($previousRun)
                    <a href="{{ route('test-results.compare', [$testResult->id, $previousRun->id]) }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                        Compare with Previous
                    </a>
                @endif
                <a href="{{ route('test-results.index') }}"
                   class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Status Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Status</p>
                <p class="mt-1">
                    @if($testResult->status === 'passed')
                        <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-green-100 text-green-700">Passed</span>
                    @elseif($testResult->status === 'failed')
                        <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-red-100 text-red-700">Failed</span>
                    @elseif($testResult->status === 'error')
                        <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-yellow-100 text-yellow-700">Error</span>
                    @else
                        <span class="inline-flex px-2.5 py-0.5 text-sm font-medium rounded bg-gray-100 text-gray-700">{{ ucfirst($testResult->status) }}</span>
                    @endif
                </p>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Total Tests</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($testResult->tests_total ?? 0) }}</p>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Passed</p>
                <p class="mt-1 text-2xl font-semibold text-green-600">{{ number_format($testResult->tests_passed ?? 0) }}</p>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-red-600">{{ number_format($testResult->tests_failed ?? 0) }}</p>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-sm font-medium text-gray-500">Duration</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $testResult->duration ? number_format($testResult->duration, 2) . 's' : 'N/A' }}</p>
            </div>
        </div>

        <!-- Failure List -->
        @if($testResult->tests_failed > 0 && !empty($testResult->failures))
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5] bg-red-50">
                    <h2 class="text-lg font-semibold text-red-700">Failed Tests ({{ count($testResult->failures) }})</h2>
                </div>
                <div class="divide-y divide-[#e5e5e5]">
                    @foreach($testResult->failures as $index => $failure)
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-sm font-medium text-gray-900">{{ $failure['test_name'] ?? 'Test ' . ($index + 1) }}</h3>
                                    @if(!empty($failure['message']))
                                        <p class="mt-2 text-sm text-red-600">{{ $failure['message'] }}</p>
                                    @endif
                                    @if(!empty($failure['stack_trace']))
                                        <pre class="mt-3 p-3 bg-gray-900 text-gray-100 rounded-lg text-xs overflow-x-auto">{{ $failure['stack_trace'] }}</pre>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Error List -->
        @if($testResult->tests_errors > 0 && !empty($testResult->errors))
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5] bg-yellow-50">
                    <h2 class="text-lg font-semibold text-yellow-700">Errors ({{ count($testResult->errors) }})</h2>
                </div>
                <div class="divide-y divide-[#e5e5e5]">
                    @foreach($testResult->errors as $index => $error)
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-sm font-medium text-gray-900">{{ $error['test_name'] ?? 'Error ' . ($index + 1) }}</h3>
                                    @if(!empty($error['message']))
                                        <p class="mt-2 text-sm text-yellow-600">{{ $error['message'] }}</p>
                                    @endif
                                    @if(!empty($error['stack_trace']))
                                        <pre class="mt-3 p-3 bg-gray-900 text-gray-100 rounded-lg text-xs overflow-x-auto">{{ $error['stack_trace'] }}</pre>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Test Output / Logs -->
        @if(!empty($testResult->output))
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-semibold text-gray-900">Test Output</h2>
                </div>
                <div class="p-6">
                    <pre class="p-4 bg-gray-900 text-gray-100 rounded-lg text-xs overflow-x-auto">{{ $testResult->output }}</pre>
                </div>
            </div>
        @endif

        <!-- Metadata -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Run Information</h2>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Run ID</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $testResult->id }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Test Suite</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $testResult->test_suite ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $testResult->created_at->format('M d, Y H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Branch</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $testResult->branch ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Commit</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $testResult->commit_hash ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Runner</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $testResult->runner ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Previous Run Comparison Link -->
        @if($previousRun)
            <div class="bg-gray-50 border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Previous Run</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Run #{{ $previousRun->id }} &bull; {{ $previousRun->created_at->format('M d, Y H:i') }}
                            &bull; {{ $previousRun->tests_passed ?? 0 }}/{{ $previousRun->tests_total ?? 0 }} passed
                        </p>
                    </div>
                    <a href="{{ route('test-results.show', $previousRun->id) }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                        View Previous Run
                    </a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
