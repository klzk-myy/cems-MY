<x-app-layout title="Accounting Periods">
    <div class="space-y-6">
        <x-page-header title="Accounting Periods" description="Manage monthly accounting periods" />

        <x-filter-bar>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <x-select name="fiscal_year" :options="$fiscalYears" placeholder="All Fiscal Years" :value="request('fiscal_year')" inline />
                <x-select name="status" :options="['Open' => 'Open', 'Closed' => 'Closed', 'Archived' => 'Archived']" placeholder="All Status" :value="request('status')" inline />
                <x-input name="search" type="text" placeholder="Search periods..." :value="request('search')" inline class="md:w-64" />
                <x-button variant="secondary" type="submit">Filter</x-button>
            </form>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Start Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">End Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Fiscal Year</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($periods as $period)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">{{ $period->period_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->start_date?->format('F Y') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->start_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->end_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $period->fiscalYear?->year_code ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge
                                    :variant="match ($period->status?->value ?? $period->status) {
                                        'Open', 'open' => 'success',
                                        'Closed', 'closed' => 'gray',
                                        default => 'info',
                                    }"
                                >
                                    {{ $period->status?->label() ?? ucfirst((string) $period->status) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if (($period->status?->value ?? $period->status) === 'Open' || ($period->status?->value ?? $period->status) === 'open')
                                    <form method="POST" action="{{ route('accounting.period.close', $period) }}"
                                          class="flex items-center justify-center gap-2"
                                          onsubmit="return confirm('Close period {{ $period->period_code }}? This cannot be undone.');">
                                        @csrf
                                        <input type="hidden" name="period_id" value="{{ $period->id }}">
                                        <input type="hidden" name="closure_date" value="{{ $period->end_date?->toDateString() }}">
                                        <input type="hidden" name="reason" value="Monthly period close">
                                        <x-button variant="ghost" size="sm" type="submit">Close</x-button>
                                    </form>
                                @else
                                    <span class="text-xs text-ink-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No periods found." :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">Showing {{ $periods->firstItem() ?? 0 }}-{{ $periods->lastItem() ?? 0 }} of {{ $periods->total() }} periods</p>
            {{ $periods->links() }}
        </div>
    </div>
</x-app-layout>
