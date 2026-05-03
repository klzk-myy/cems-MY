<div class="p-6">
    <div class="mb-6">
        <a href="{{ route('test-results.index') }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back to Test Results</a>
    </div>

    <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">Test Result #{{ $testResult->id ?? '1' }}</h1>
            <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800">Failed</span>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <p class="text-sm text-[--color-text-muted]">Test Name</p>
                <p class="font-medium">{{ $testResult->name ?? 'Currency Conversion Test' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-text-muted]">Duration</p>
                <p class="font-medium">{{ $testResult->duration ?? '245' }}ms</p>
            </div>
            <div>
                <p class="text-sm text-[--color-text-muted]">Executed At</p>
                <p class="font-medium">{{ $testResult->created_at ?? now()->format('Y-m-d H:i:s') }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-text-muted]">Status</p>
                <p class="font-medium text-red-600">Failed</p>
            </div>
        </div>

        <div class="border-t border-[--color-border] pt-6">
            <h2 class="text-lg font-medium mb-4">Error Details</h2>
            <pre class="bg-[--color-bg-tertiary] p-4 rounded-lg text-sm overflow-x-auto">{{ $testResult->error_message ?? 'Expected: 100.00\nActual: 99.50\nDifference exceeds threshold' }}</pre>
        </div>
    </div>

    <div class="flex gap-4 mt-6">
        <button class="px-4 py-2 bg-[--color-accent] text-white rounded-lg hover:opacity-90">Re-run Test</button>
        <a href="{{ route('test-results.compare') }}" class="px-4 py-2 border border-[--color-border] rounded-lg hover:bg-[--color-bg-tertiary]">Compare Results</a>
    </div>
</div>