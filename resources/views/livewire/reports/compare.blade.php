<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Compare Reports</h1>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form wire:submit="compare" class="grid grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Report 1</label>
                    <select wire:model="report1_id" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="">Select report</option>
                        @foreach($allReports as $report)
                        <option value="{{ $report->id }}">{{ $report->report_number }} - {{ $report->period }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Report 2</label>
                    <select wire:model="report2_id" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="">Select report</option>
                        @foreach($allReports as $report)
                        <option value="{{ $report->id }}">{{ $report->report_number }} - {{ $report->period }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Report Type</label>
                    <select wire:model="reportType" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="msb2">MSB2</option>
                        <option value="lmca">LMCA</option>
                        <option value="lctr">LCTR</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded w-full">Compare</button>
                </div>
            </form>
        </div>

        @if($comparisonResult)
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Comparison Results</h2>

            <div class="grid grid-cols-3 gap-6 mb-6">
                <div class="border border-[var(--color-border)] rounded p-4">
                    <h3 class="font-medium text-[var(--color-ink)] mb-2">Report 1</h3>
                    <p class="text-sm text-gray-500">{{ $comparisonResult['report1']->report_number }}</p>
                    <p class="text-2xl font-bold mt-2">${{ number_format($comparisonResult['report1']->total_value, 2) }}</p>
                </div>

                <div class="border border-[var(--color-border)] rounded p-4">
                    <h3 class="font-medium text-[var(--color-ink)] mb-2">Report 2</h3>
                    <p class="text-sm text-gray-500">{{ $comparisonResult['report2']->report_number }}</p>
                    <p class="text-2xl font-bold mt-2">${{ number_format($comparisonResult['report2']->total_value, 2) }}</p>
                </div>

                <div class="border border-[var(--color-border)] rounded p-4">
                    <h3 class="font-medium text-[var(--color-ink)] mb-2">Difference</h3>
                    <p class="text-2xl font-bold mt-2 {{ $comparisonResult['difference'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $comparisonResult['difference'] >= 0 ? '+' : '' }}${{ number_format($comparisonResult['difference'], 2) }}
                    </p>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>