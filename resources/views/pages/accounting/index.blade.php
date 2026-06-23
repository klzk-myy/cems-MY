<x-app-layout title="Accounting">
    <div class="p-6 space-y-6">
        <x-page-header title="Accounting Dashboard" />

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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-card title="Trial Balance">
                <x-button variant="secondary" size="sm" href="{{ route('accounting.trial-balance') }}">View Report</x-button>
            </x-card>
            <x-card title="Profit & Loss">
                <x-button variant="secondary" size="sm" href="{{ route('accounting.profit-loss') }}">View Report</x-button>
            </x-card>
            <x-card title="Balance Sheet">
                <x-button variant="secondary" size="sm" href="{{ route('accounting.balance-sheet') }}">View Report</x-button>
            </x-card>
        </div>
    </div>
</x-app-layout>
