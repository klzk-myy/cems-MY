<x-app-layout title="Fiscal Years">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Fiscal Years</h1>
                <p class="mt-1 text-sm text-ink-muted">Manage accounting fiscal years and periods</p>
            </div>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                + Create Fiscal Year
            </button>
        </div>

        @php
            $activeYear = $fiscalYears->first();
        @endphp

        <!-- Active Fiscal Year -->
        <div class="bg-surface border border-border rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-ink-muted">Active Fiscal Year</h3>
                    <p class="mt-1 text-2xl font-semibold text-ink">
                        {{ $activeYear ? 'FY '.$activeYear->year_code : 'No fiscal years configured' }}
                    </p>
                    <p class="mt-1 text-sm text-ink-muted">
                        @if ($activeYear)
                            {{ $activeYear->start_date?->format('F j, Y') }} - {{ $activeYear->end_date?->format('F j, Y') }}
                        @endif
                    </p>
                </div>
                @if ($activeYear)
                    <div class="flex items-center gap-3">
                        @php
                            $activeYearStatusColor = match ($activeYear->status?->value) {
                                'Open' => 'bg-green-100 text-green-700',
                                'Closed' => 'bg-canvas-subtle text-ink-muted',
                                'Archived' => 'bg-blue-100 text-blue-700',
                                default => 'bg-canvas-subtle text-ink-muted',
                            };
                        @endphp
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $activeYearStatusColor }}">
                            {{ $activeYear->status?->label() }}
                        </span>
                        <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">Close Year</button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Fiscal Years Table -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-canvas-subtle border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Fiscal Year</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Start Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">End Date</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Periods</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($fiscalYears as $fiscalYear)
                        @php
                            $yearStatusColor = match ($fiscalYear->status?->value) {
                                'Open' => 'bg-green-100 text-green-700',
                                'Closed' => 'bg-canvas-subtle text-ink-muted',
                                'Archived' => 'bg-blue-100 text-blue-700',
                                default => 'bg-canvas-subtle text-ink-muted',
                            };
                        @endphp
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">FY {{ $fiscalYear->year_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $fiscalYear->start_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $fiscalYear->end_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center text-sm">{{ $fiscalYear->periods?->count() ?? 0 }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $yearStatusColor }}">
                                    {{ $fiscalYear->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button class="text-blue-600 hover:text-blue-800">View</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-sm text-center text-ink-muted">No fiscal years found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Periods Summary -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-border">
                <h3 class="text-sm font-medium text-ink">
                    Current Periods{{ $activeYear ? ' - FY '.$activeYear->year_code : '' }}
                </h3>
            </div>
            <table class="w-full">
                <thead class="bg-canvas-subtle border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Period</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Start Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">End Date</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse (($activeYear?->periods ?? collect())->sortBy('period_code') as $period)
                        @php
                            $periodStatusColor = match ($period->status?->value) {
                                'open' => 'bg-green-100 text-green-700',
                                'closed' => 'bg-canvas-subtle text-ink-muted',
                                default => 'bg-canvas-subtle text-ink-muted',
                            };
                            $isCurrent = now()->between($period->start_date, $period->end_date);
                        @endphp
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $period->period_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->start_date?->format('F') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->start_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->end_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $periodStatusColor }}">
                                    {{ $period->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button class="text-blue-600 hover:text-blue-800">
                                    {{ $isCurrent ? 'Close' : 'View' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-sm text-center text-ink-muted">No periods found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
