<div class="p-6">
    <div class="mb-6">
        <a href="{{ route('test-results.index') }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back to Test Results</a>
    </div>

    <h1 class="text-2xl font-semibold mb-6">Compare Test Results</h1>

    <div class="grid grid-cols-2 gap-6 mb-6">
        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
            <h2 class="text-lg font-medium mb-4">Result A</h2>
            <select class="w-full px-3 py-2 border border-[--color-border] rounded-lg bg-[--color-bg-primary]">
                <option value="">Select first result...</option>
                @foreach($testResults ?? [] as $result)
                <option value="{{ $result->id }}">#{{ $result->id }} - {{ $result->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
            <h2 class="text-lg font-medium mb-4">Result B</h2>
            <select class="w-full px-3 py-2 border border-[--color-border] rounded-lg bg-[--color-bg-primary]">
                <option value="">Select second result...</option>
                @foreach($testResults ?? [] as $result)
                <option value="{{ $result->id }}">#{{ $result->id }} - {{ $result->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
        <h2 class="text-lg font-medium mb-4">Comparison Summary</h2>
        <table class="w-full">
            <thead class="bg-[--color-bg-tertiary]">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium">Metric</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Result A</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Result B</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Difference</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[--color-border]">
                <tr>
                    <td class="px-4 py-3 text-sm">Duration</td>
                    <td class="px-4 py-3 text-sm">245ms</td>
                    <td class="px-4 py-3 text-sm">230ms</td>
                    <td class="px-4 py-3 text-sm text-green-600">-15ms (6.1%)</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 text-sm">Memory</td>
                    <td class="px-4 py-3 text-sm">12.4 MB</td>
                    <td class="px-4 py-3 text-sm">11.8 MB</td>
                    <td class="px-4 py-3 text-sm text-green-600">-0.6 MB (4.8%)</td>
                </tr>
                <tr>
                    <td class="px-4 py-3 text-sm">Status</td>
                    <td class="px-4 py-3 text-sm">Failed</td>
                    <td class="px-4 py-3 text-sm">Passed</td>
                    <td class="px-4 py-3 text-sm text-yellow-600">Improved</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex gap-4">
        <button class="px-4 py-2 bg-[--color-accent] text-white rounded-lg hover:opacity-90">Generate Report</button>
        <a href="{{ route('test-results.statistics') }}" class="px-4 py-2 border border-[--color-border] rounded-lg hover:bg-[--color-bg-tertiary]">View Statistics</a>
    </div>
</div>