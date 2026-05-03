<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Position Limit Report</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <form wire:submit="generate" class="mb-6">
                <div class="grid grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)]">As of Date</label>
                        <input type="date" wire:model="asOfDate" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)]">Branch</label>
                        <select wire:model="branchId" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                            <option value="">All Branches</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded w-full">Generate</button>
                    </div>
                </div>
            </form>

            @if($reportGenerated)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Currency</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Net Position</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Limit</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Utilization</th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($positions as $position)
                        <tr class="border-t border-[var(--color-border)]">
                            <td class="px-4 py-3">{{ $position['currency'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($position['net_position']) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($position['limit']) }}</td>
                            <td class="px-4 py-3 text-right">{{ $position['utilization'] }}%</td>
                            <td class="px-4 py-3 text-right">
                                @if($position['utilization'] >= 100)
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">Exceeded</span>
                                @elseif($position['utilization'] >= 80)
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Warning</span>
                                @else
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">OK</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-center">No positions found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>