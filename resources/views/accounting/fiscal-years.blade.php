<x-app-layout title="Fiscal Years">
    <div class="space-y-6">
        <x-page-header
            title="Fiscal Years"
            description="Manage accounting fiscal years and periods"
            :actions="true"
        >
            <x-slot:actions>
                <x-button variant="primary">+ Create Fiscal Year</x-button>
            </x-slot:actions>
        </x-page-header>

        @php
            $activeYear = $fiscalYears->first();
        @endphp

        <x-card>
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
                        <x-badge
                            :variant="match ($activeYear->status?->value) {
                                'Open' => 'success',
                                'Closed' => 'gray',
                                'Archived' => 'info',
                                default => 'gray',
                            }"
                        >
                            {{ $activeYear->status?->label() }}
                        </x-badge>
                        <x-button variant="secondary" size="sm">Close Year</x-button>
                    </div>
                @endif
            </div>
        </x-card>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Fiscal Year</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Start Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">End Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Periods</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($fiscalYears as $fiscalYear)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">FY {{ $fiscalYear->year_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $fiscalYear->start_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $fiscalYear->end_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center text-sm">{{ $fiscalYear->periods?->count() ?? 0 }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge
                                    :variant="match ($fiscalYear->status?->value) {
                                        'Open' => 'success',
                                        'Closed' => 'gray',
                                        'Archived' => 'info',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $fiscalYear->status?->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-button variant="ghost" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No fiscal years found." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Current Periods{{ $activeYear ? ' - FY '.$activeYear->year_code : '' }}">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Start Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">End Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse (($activeYear?->periods ?? collect())->sortBy('period_code') as $period)
                        @php
                            $isCurrent = now()->between($period->start_date, $period->end_date);
                        @endphp
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $period->period_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->start_date?->format('F') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->start_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->end_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge
                                    :variant="match ($period->status?->value) {
                                        'open' => 'success',
                                        'closed' => 'gray',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $period->status?->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-button variant="ghost" size="sm">
                                    {{ $isCurrent ? 'Close' : 'View' }}
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No periods found." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
