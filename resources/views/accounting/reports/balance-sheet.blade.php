<x-app-layout title="Balance Sheet">
    <div class="space-y-6">
        <x-page-header title="Balance Sheet" :actions="true">
            As of {{ $asOfDate }}

            <x-slot:actions>
                <form method="GET" class="flex items-center gap-3">
                    <x-input type="date" name="as_of_date" :value="$asOfDate" inline />
                    <x-button variant="primary" type="submit">Refresh</x-button>
                </form>
            </x-slot:actions>
        </x-page-header>

        @php
            $assets = $balanceSheet['assets'] ?? [];
            $liabilities = $balanceSheet['liabilities'] ?? [];
            $equity = $balanceSheet['equity'] ?? [];
            $totalAssets = $balanceSheet['total_assets'] ?? '0';
            $totalLiabilities = $balanceSheet['total_liabilities'] ?? '0';
            $totalEquity = $balanceSheet['total_equity'] ?? '0';
            $totalLiabilitiesEquity = $balanceSheet['total_liabilities_equity'] ?? '0';
            $isBalanced = $balanceSheet['is_balanced'] ?? false;
        @endphp

        @if ($isBalanced)
            <x-alert type="success" title="✓ Balance Sheet is balanced">
                Assets: RM {{ number_format((float) $totalAssets, 2) }} = Liabilities + Equity: RM {{ number_format((float) $totalLiabilitiesEquity, 2) }}
            </x-alert>
        @else
            <x-alert type="error" title="✗ Balance Sheet is NOT balanced" />
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Assets">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse ($assets as $asset)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink">{{ $asset['account_code'] }} - {{ $asset['account_name'] }}</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $asset['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <x-empty-state message="No assets" :colspan="2" />
                        @endforelse
                        <tr class="font-semibold bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink">Total Assets</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $totalAssets, 2) }}</td>
                        </tr>
                    </x-slot:tbody>
                </x-table>
            </x-card>

            <div class="space-y-6">
                <x-card title="Liabilities">
                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @forelse ($liabilities as $liability)
                                <tr class="hover:bg-canvas-subtle">
                                    <td class="px-4 py-3 text-sm text-ink">{{ $liability['account_code'] }} - {{ $liability['account_name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $liability['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <x-empty-state message="No liabilities" :colspan="2" />
                            @endforelse
                            <tr class="font-semibold bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink">Total Liabilities</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $totalLiabilities, 2) }}</td>
                            </tr>
                        </x-slot:tbody>
                    </x-table>
                </x-card>

                <x-card title="Equity">
                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @forelse ($equity as $eq)
                                <tr class="hover:bg-canvas-subtle">
                                    <td class="px-4 py-3 text-sm text-ink">{{ $eq['account_code'] }} - {{ $eq['account_name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $eq['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <x-empty-state message="No equity accounts" :colspan="2" />
                            @endforelse
                            <tr class="font-semibold bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink">Total Equity</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $totalEquity, 2) }}</td>
                            </tr>
                        </x-slot:tbody>
                    </x-table>
                </x-card>

                <x-card>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-ink">Total Liabilities & Equity</p>
                        <p class="text-lg font-semibold text-ink">RM {{ number_format((float) $totalLiabilitiesEquity, 2) }}</p>
                    </div>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
