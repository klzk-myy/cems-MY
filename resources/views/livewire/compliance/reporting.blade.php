<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Compliance Reporting</h1>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-medium text-[var(--color-ink)] mb-4">Generate Report</h3>
                <form wire:submit="generate">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-[var(--color-ink)]">Report Type</label>
                        <select wire:model="reportType" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                            <option value="ctos">CTO Report</option>
                            <option value="edd">EDD Report</option>
                            <option value="sanctions">Sanctions Screening</option>
                            <option value="cases">Cases Summary</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-[var(--color-ink)]">Date Range</label>
                        <div class="flex gap-4">
                            <input type="date" wire:model="startDate" class="flex-1 rounded border border-[var(--color-border)] px-3 py-2" />
                            <input type="date" wire:model="endDate" class="flex-1 rounded border border-[var(--color-border)] px-3 py-2" />
                        </div>
                    </div>

                    <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Generate</button>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-medium text-[var(--color-ink)] mb-4">Recent Reports</h3>
                <table class="w-full">
                    <tbody>
                        @forelse($recentReports as $report)
                        <tr class="border-b border-[var(--color-border)] py-2">
                            <td class="text-sm">{{ $report->name }}</td>
                            <td class="text-sm text-gray-500">{{ $report->created_at->format('Y-m-d') }}</td>
                            <td>
                                <button wire:click="download({{ $report->id }})" class="text-blue-600 hover:underline text-sm">Download</button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td class="text-center py-2">No reports generated</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>