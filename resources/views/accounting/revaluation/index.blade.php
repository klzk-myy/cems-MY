<x-app-layout title="Currency Revaluation">
    <div class="space-y-6">
        <x-page-header title="Currency Revaluation" description="Month-end revaluation of foreign currency positions">
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('accounting.revaluation.history') }}">History</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-medium text-ink-muted">This Month ({{ $status['month'] }})</h3>
                    <p class="mt-1 text-lg font-semibold text-ink">
                        @if ($status['has_run'])
                            Revaluation run — {{ $status['entries_count'] }} entries ({{ implode(', ', $status['currencies']) }})
                        @else
                            Not yet run for this month
                        @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('accounting.revaluation.run') }}"
                      data-confirm="Run revaluation for {{ $status['month'] }}? This posts journal entries for unrealized gains/losses.">
                    @csrf
                    <x-button variant="primary" type="submit">Run Revaluation</x-button>
                </form>
            </div>
        </x-card>

        <x-card title="Foreign Currency Positions">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Branch</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Quantity</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Cost</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Current Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Current Value</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Unrealized P&L</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Revalued</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($positions as $position)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">{{ $position->currency_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $position->branch_id }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $position->quantity, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $position->average_cost, 8) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $position->current_rate, 8) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $position->current_value, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ (float) $position->unrealized_gain_loss < 0 ? 'text-danger' : ((float) $position->unrealized_gain_loss > 0 ? 'text-success' : '') }}">
                                {{ number_format((float) $position->unrealized_gain_loss, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $position->last_revalued_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No foreign currency positions." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
