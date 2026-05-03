<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Quarterly LVR Report</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <form wire:submit="generate">
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)]">Year</label>
                        <select wire:model="year" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                            @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)]">Quarter</label>
                        <select wire:model="quarter" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                            <option value="1">Q1 (Jan-Mar)</option>
                            <option value="2">Q2 (Apr-Jun)</option>
                            <option value="3">Q3 (Jul-Sep)</option>
                            <option value="4">Q4 (Oct-Dec)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Generate Report</button>
            </form>

            @if($report)
            <div class="mt-6 border-t border-[var(--color-border)] pt-6">
                <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Quarterly Summary</h2>

                <div class="grid grid-cols-4 gap-6 mb-6">
                    <div>
                        <label class="block text-sm text-gray-500">Total Loans</label>
                        <p class="text-2xl font-bold">${{ number_format($report->total_loans, 2) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500">Total Value</label>
                        <p class="text-2xl font-bold">${{ number_format($report->total_value, 2) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500">LVR Average</label>
                        <p class="text-2xl font-bold">{{ number_format($report->avg_lvr, 1) }}%</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500">Compliant Loans</label>
                        <p class="text-2xl font-bold text-green-600">{{ $report->compliant_count }}</p>
                    </div>
                </div>

                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Month</th>
                            <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Loans</th>
                            <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Value</th>
                            <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Avg LVR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report->monthlyData as $month)
                        <tr class="border-t border-[var(--color-border)]">
                            <td class="px-4 py-2">{{ $month['name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $month['count'] }}</td>
                            <td class="px-4 py-2 text-right">${{ number_format($month['value'], 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($month['lvr'], 1) }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>