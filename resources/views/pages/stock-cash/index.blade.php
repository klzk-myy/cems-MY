<x-app-layout title="Stock & Cash">
    <div class="space-y-6">
        <x-page-header
            title="Stock & Cash Positions"
            description="Overview of currency positions and till summaries"
        />

        <x-card title="Currency Positions">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Available</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Held</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Total</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">P&L</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($positions ?? [] as $position)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium">{{ $position->currency_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format($position->available, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format($position->held, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format($position->total, 2) }}</td>
                            <td class="px-4 py-3 text-sm {{ $position->pnl >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                                {{ number_format($position->pnl, 2) }}
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No positions found." :colspan="5" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-card title="Today's Till Summary">
                <x-card-section>
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Open Tills</dt>
                            <dd class="font-medium">{{ count($openTills ?? []) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Closed Tills</dt>
                            <dd class="font-medium">{{ count($closedTills ?? []) }}</dd>
                        </div>
                    </dl>
                </x-card-section>
            </x-card>

            <x-card title="Variance">
                <x-card-section>
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Total Variance</dt>
                            <dd class="font-medium {{ $totalPnl >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                                {{ number_format($totalPnl ?? 0, 2) }} MYR
                            </dd>
                        </div>
                    </dl>
                </x-card-section>
            </x-card>
        </div>
    </div>
</x-app-layout>
