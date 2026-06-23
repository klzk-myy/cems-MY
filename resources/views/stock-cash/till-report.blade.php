<x-app-layout title="Till Report - {{ $date }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Till Report"
            description="Report Date: {{ $date }}"
            :actions="true"
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
                        {{ $balances->counter->name ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Opening Float</dt>
                    <dd class="mt-1 text-sm text-ink">
                        MYR {{ number_format((float) $balances->opening_float, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Closing Float</dt>
                    <dd class="mt-1 text-sm text-ink">
                        MYR {{ number_format((float) $balances->closing_float, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Opening By</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $balances->opener->name ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Closing By</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $balances->closer->name ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Status</dt>
                    <dd class="mt-1 text-sm">
                        <x-badge
                            :variant="match ($balances->status->value) {
                                'closed' => 'success',
                                'open' => 'info',
                                default => 'gray',
                            }"
                        >
                            {{ ucfirst($balances->status->value) }}
                        </x-badge>
                    </dd>
                </div>
            </div>
        </x-card>

        <x-card title="Currency Balances">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Opening Balance</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Total Bought</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Total Sold</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Closing Balance</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Variance</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($balances->currencyBalances ?? [] as $currencyBalance)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">
                                {{ $currencyBalance->currency->code ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $currencyBalance->opening_balance, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $currencyBalance->total_bought, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $currencyBalance->total_sold, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink text-right">
                                {{ number_format((float) $currencyBalance->closing_balance, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right">
                                @php
                                    $variance = (float) $currencyBalance->variance;
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
