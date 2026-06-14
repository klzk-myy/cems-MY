<x-app-layout title="Quarterly Large Value Report">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Quarterly Large Value Report</h1>
                <p class="text-sm text-ink-muted mt-1">QLVR - Quarterly Large Value Transaction Report</p>
            </div>
            @if($reportGenerated)
            <div class="flex gap-3">
                <button onclick="window.print()" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                    Print
                </button>
                <form method="POST" action="{{ route('reports.quarterly-lvr.export', ['quarter' => $quarter]) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                        Export
                    </button>
                </form>
            </div>
            @endif
        </div>

        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.quarterly-lvr') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="quarter" class="text-sm font-medium text-ink-muted">Select Quarter</label>
                    <select id="quarter" name="quarter" class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                        @for($y = date('Y'); $y >= date('Y') - 2; $y--)
                            <option value="{{ $y }}-Q1" {{ $quarter === $y . '-Q1' ? 'selected' : '' }}>{{ $y }} Q1 (Jan - Mar)</option>
                            <option value="{{ $y }}-Q2" {{ $quarter === $y . '-Q2' ? 'selected' : '' }}>{{ $y }} Q2 (Apr - Jun)</option>
                            <option value="{{ $y }}-Q3" {{ $quarter === $y . '-Q3' ? 'selected' : '' }}>{{ $y }} Q3 (Jul - Sep)</option>
                            <option value="{{ $y }}-Q4" {{ $quarter === $y . '-Q4' ? 'selected' : '' }}>{{ $y }} Q4 (Oct - Dec)</option>
                        @endfor
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Generate Report
                </button>
            </form>
        </div>

        @if($reportGenerated && !empty($reportData))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Total Transactions</p>
                <p class="text-2xl font-semibold text-ink">{{ number_format($reportData['total_transactions'] ?? 0) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Total Volume (MYR)</p>
                <p class="text-2xl font-semibold text-ink">{{ number_format($reportData['total_volume'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Average Value</p>
                <p class="text-2xl font-semibold text-ink">{{ number_format($reportData['average_value'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Report Status</p>
                <p class="text-2xl font-semibold text-green-600">Complete</p>
            </div>
        </div>

        @if(!empty($reportData['monthly_breakdown']))
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Monthly Breakdown</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th class="text-left py-3 px-4 font-medium text-ink-muted">Month</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Transactions</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Volume (MYR)</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Average (MYR)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData['monthly_breakdown'] as $month)
                    <tr class="border-b border-border hover:bg-canvas-subtle">
                        <td class="py-3 px-4 text-ink font-medium">{{ $month['label'] }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($month['transaction_count']) }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($month['volume'], 2) }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($month['average'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        @elseif($reportGenerated && empty($reportData))
        <div class="bg-surface border border-border rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-ink mb-2">No Report Data</h3>
            <p class="text-sm text-ink-muted">No high-value transactions found for the selected quarter.</p>
        </div>
        @else
        <div class="bg-surface border border-border rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-ink mb-2">Select a Quarter</h3>
            <p class="text-sm text-ink-muted">Choose a quarter above to generate the LVR report.</p>
        </div>
        @endif
    </div>
</x-app-layout>