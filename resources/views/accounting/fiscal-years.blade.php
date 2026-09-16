<x-app-layout title="Fiscal Years">
    <div class="space-y-6">
        <x-page-header
            title="Fiscal Years"
            description="Manage accounting fiscal years and periods"
        />

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
                    </div>
                @endif
            </div>
        </x-card>

        @if (auth()->user()->role->canPerform(\App\Enums\Permission::ManageAccounting))
        <x-card title="Create Fiscal Year">
            <form method="POST" action="{{ route('accounting.fiscal-years.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <x-input name="year_code" label="Year Code" placeholder="e.g. FY2028" required maxlength="10" inline />
                <x-input name="year" label="Year (optional)" type="number" min="2000" max="2100" placeholder="Derives dates from config" inline />
                <x-input name="start_date" label="Start Date (optional)" type="date" inline />
                <x-input name="end_date" label="End Date (optional)" type="date" inline />
                <x-button type="submit" variant="primary">Create Fiscal Year</x-button>
            </form>
            <p class="mt-2 text-xs text-ink-muted">When only a year is given, dates are derived from the configured fiscal year-end. Monthly periods are created automatically.</p>
        </x-card>
        @endif

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
                                @if ($fiscalYear->status?->value === 'Open' && auth()->user()->role->canPerform(\App\Enums\Permission::ManageAccounting))
                                    <form method="POST" action="{{ route('accounting.fiscal-years.close', $fiscalYear) }}"
                                          class="flex items-center justify-center gap-2"
                                          data-confirm="Close {{ $fiscalYear->year_code }}? All periods must be closed and this cannot be undone.">
                                        @csrf
                                        <input type="text" name="confirm_code" placeholder="Type {{ $fiscalYear->year_code }}" required
                                               class="w-32 px-2 py-1 text-xs bg-canvas-subtle border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-ink">
                                        <x-button variant="danger" size="sm" type="submit">Close Year</x-button>
                                    </form>
                                @else
                                    <span class="text-xs text-ink-muted">—</span>
                                @endif
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
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $period->period_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->start_date?->format('F') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->start_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->end_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge
                                    :variant="match ($period->status?->value) {
                                        'Open' => 'success',
                                        'Closed' => 'gray',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $period->status?->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($period->status?->value === 'Open' && auth()->user()->role->canPerform(\App\Enums\Permission::ManageAccounting))
                                    <form method="POST" action="{{ route('accounting.period.close', $period) }}"
                                          data-confirm="Close period {{ $period->period_code }}?">
                                        @csrf
                                        <x-button variant="ghost" size="sm" type="submit">Close</x-button>
                                    </form>
                                @elseif ($period->status?->value === 'Open')
                                    <span class="text-xs text-ink-muted">—</span>
                                @else
                                    <span class="text-xs text-ink-muted">Closed</span>
                                @endif
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
