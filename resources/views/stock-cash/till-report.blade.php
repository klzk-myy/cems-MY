<x-app-layout title="Till Report - {{ $date }}">
    @php
        $till = $balances->first();
        $myrRow = $balances->firstWhere('currency_code', 'MYR');
        $isOpen = $till && $till->closed_at === null;
    @endphp
    <div class="space-y-6">
        <x-page-header
            title="Till Report"
            description="Report Date: {{ $date }}"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ url()->previous() }}">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Till Balance Details">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Till / Counter</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $till->counter->name ?? $till->till_id ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Opening Float (MYR)</dt>
                    <dd class="mt-1 text-sm text-ink">
                        MYR {{ number_format((float) ($myrRow->opening_balance ?? 0), 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Closing Float (MYR)</dt>
                    <dd class="mt-1 text-sm text-ink">
                        MYR {{ number_format((float) ($myrRow->closing_balance ?? 0), 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Opened By</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $till->opener->username ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Closed By</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $till->closer->username ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Status</dt>
                    <dd class="mt-1 text-sm">
                        <x-badge :variant="$isOpen ? 'info' : 'success'">
                            {{ $isOpen ? 'Open' : 'Closed' }}
                        </x-badge>
                    </dd>
                </div>
            </div>
        </x-card>

        <x-card title="Currency Balances">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Opening Balance</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Total Bought</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Total Sold</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Closing Balance</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Variance</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($balances as $balance)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">
                                {{ $balance->currency->code ?? $balance->currency_code }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $balance->opening_balance, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) ($balance->buy_quantity ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) ($balance->sell_quantity ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) ($balance->closing_balance ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right">
                                @php
                                    $variance = (float) ($balance->variance ?? 0);
                                @endphp
                                <span class="@if($variance != 0) text-danger-text font-medium @else text-ink @endif">
                                    {{ number_format($variance, 2) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No currency balances found for this till." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
