<x-app-layout title="My Stock & Cash">
    <div class="space-y-6">
        <x-page-header title="My Stock & Cash" description="Daily stock position and cash movement for {{ $date->format('d M Y') }}">
            <x-slot:actions>
                <form method="GET" action="{{ route('my-stock.index') }}" class="flex items-center gap-2">
                    <input
                        type="date"
                        name="date"
                        value="{{ $date->format('Y-m-d') }}"
                        max="{{ now()->format('Y-m-d') }}"
                        class="rounded-md border-border bg-surface text-ink text-sm"
                    />
                    <x-button type="submit" variant="secondary">View</x-button>
                    @if(! $date->isToday())
                        <x-button href="{{ route('my-stock.index') }}" variant="secondary">Today</x-button>
                    @endif
                </form>
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid>
            <x-stat-card label="Total Value Held" color="blue" value="MYR {{ number_format($valuation['total_myr'], 2) }}" />
            <x-stat-card label="MYR Cash" color="green" value="MYR {{ number_format($valuation['cash_myr'], 2) }}" />
            <x-stat-card label="Foreign Stock Value" color="purple" value="MYR {{ number_format($valuation['stock_myr'], 2) }}" />
        </x-stat-grid>

        <x-card title="Foreign Currency Stock">
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3">Currency</th>
                        <th class="px-4 py-3 text-right">Opening</th>
                        <th class="px-4 py-3 text-right">Buy</th>
                        <th class="px-4 py-3 text-right">RM Cr</th>
                        <th class="px-4 py-3 text-right">Sell</th>
                        <th class="px-4 py-3 text-right">RM Dr</th>
                        <th class="px-4 py-3 text-right">Current</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($rows as $row)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 font-medium">{{ $row['currency_code'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['opening'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-success">{{ number_format($row['buy_quantity'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['buy_myr'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-danger">{{ number_format($row['sell_quantity'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['sell_myr'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ number_format($row['current'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-3 text-sm text-ink-muted">
                                <x-empty-state title="No stock activity" description="No allocations or settled transactions for this date." />
                            </td>
                        </tr>
                    @endforelse

                    <tr class="border-t border-border font-medium">
                        <td class="px-4 py-3">MYR</td>
                        <td class="px-4 py-3 text-right">{{ number_format($myrRow['opening'], 2) }}</td>
                        <td class="px-4 py-3 text-right">&mdash;</td>
                        <td class="px-4 py-3 text-right">{{ number_format($myrRow['buy_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-right">&mdash;</td>
                        <td class="px-4 py-3 text-right">{{ number_format($myrRow['sell_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($myrRow['current'], 2) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-card>

        <x-alert type="info" title="How to read this sheet">
            <p><strong>Buy / RM Cr</strong> — foreign currency bought in from customers and the MYR paid out for it.</p>
            <p><strong>Sell / RM Dr</strong> — foreign currency sold to customers and the MYR received.</p>
            <p class="mt-1"><strong>Current</strong> = Opening + Buy &minus; Sell. For MYR cash: Opening &minus; RM Cr + RM Dr.</p>
            <p class="mt-1"><strong>Foreign Stock Value</strong> converts each currency's Current holding to MYR at the latest board sell rate.
                @if($valuation['unvalued'] !== [])
                    <span class="text-warning">No active rate for: {{ implode(', ', $valuation['unvalued']) }} — excluded from the total.</span>
                @endif
            </p>
        </x-alert>
    </div>
</x-app-layout>
