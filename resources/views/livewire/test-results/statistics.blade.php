<div class="p-6">
    <div class="mb-6">
        <a href="{{ route('test-results.index') }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back to Test Results</a>
    </div>

    <h1 class="text-2xl font-semibold mb-6">Test Statistics</h1>

    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-4">
            <p class="text-sm text-[--color-text-muted]">Total Tests</p>
            <p class="text-2xl font-bold">156</p>
        </div>
        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-4">
            <p class="text-sm text-[--color-text-muted]">Passed</p>
            <p class="text-2xl font-bold text-green-600">142</p>
        </div>
        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-4">
            <p class="text-sm text-[--color-text-muted]">Failed</p>
            <p class="text-2xl font-bold text-red-600">14</p>
        </div>
        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-4">
            <p class="text-sm text-[--color-text-muted]">Pass Rate</p>
            <p class="text-2xl font-bold">91%</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
            <h2 class="text-lg font-medium mb-4">Test Duration Trend</h2>
            <div class="h-48 flex items-end gap-2">
                @foreach([65, 80, 72, 90, 85, 78, 95, 88, 76, 82] as $height)
                <div class="flex-1 bg-[--color-accent] rounded-t" style="height: {{ $height }}%"></div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2 text-xs text-[--color-text-muted]">
                <span>Jan</span>
                <span>Feb</span>
                <span>Mar</span>
                <span>Apr</span>
                <span>May</span>
            </div>
        </div>

        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
            <h2 class="text-lg font-medium mb-4">Failure Distribution</h2>
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left text-sm font-medium pb-2">Category</th>
                        <th class="text-right text-sm font-medium pb-2">Count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[--color-border]">
                    <tr>
                        <td class="py-2 text-sm">Conversion Accuracy</td>
                        <td class="py-2 text-sm text-right">8</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-sm">Rate Limiting</td>
                        <td class="py-2 text-sm text-right">3</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-sm">API Timeout</td>
                        <td class="py-2 text-sm text-right">2</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-sm">Database Constraint</td>
                        <td class="py-2 text-sm text-right">1</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex gap-4">
        <a href="{{ route('test-results.compare') }}" class="px-4 py-2 border border-[--color-border] rounded-lg hover:bg-[--color-bg-tertiary]">Compare Results</a>
        <button class="px-4 py-2 bg-[--color-accent] text-white rounded-lg hover:opacity-90">Export Report</button>
    </div>
</div>