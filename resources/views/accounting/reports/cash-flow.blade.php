<x-app-layout title="Cash Flow Statement">
    <div class="space-y-6">
        <x-page-header title="Cash Flow Statement" description="Cash movement by operating, investing, and financing activities">
            <x-slot:actions>
                <x-button variant="secondary" onclick="window.print()">Print</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <x-input name="from" label="From" type="date" :value="$from" inline />
                <x-input name="to" label="To" type="date" :value="$to" inline />
                <x-button variant="secondary" type="submit">Apply</x-button>
            </form>
        </x-filter-bar>

        <x-stat-grid cols="4">
            <x-stat-card label="Operating" :value="'RM '.number_format((float) $data['operating_total'], 2)" :color="(float) $data['operating_total'] < 0 ? 'red' : 'green'" />
            <x-stat-card label="Investing" :value="'RM '.number_format((float) $data['investing_total'], 2)" :color="(float) $data['investing_total'] < 0 ? 'red' : 'green'" />
            <x-stat-card label="Financing" :value="'RM '.number_format((float) $data['financing_total'], 2)" :color="(float) $data['financing_total'] < 0 ? 'red' : 'green'" />
            <x-stat-card label="Net Change in Cash" :value="'RM '.number_format((float) $data['net_change_in_cash'], 2)" :color="(float) $data['net_change_in_cash'] < 0 ? 'red' : 'green'" />
        </x-stat-grid>

        <x-card title="Operating Activities">
            <x-table>
                <x-slot:tbody>
                    @foreach ([
                        'Net Income' => 'net_income',
                        'Depreciation' => 'depreciation',
                        'Amortization' => 'amortization',
                        'Change in Receivables' => 'ar_change',
                        'Change in Payables' => 'ap_change',
                        'Change in Inventory' => 'inventory_change',
                    ] as $label => $key)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $label }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) ($data['operating_activities'][$key] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t border-border font-semibold">
                        <td class="px-4 py-3 text-sm">Net Cash from Operating</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $data['operating_total'], 2) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Investing Activities">
            <x-table>
                <x-slot:tbody>
                    @foreach ([
                        'Asset Purchases' => 'asset_purchases',
                        'Asset Sales' => 'asset_sales',
                    ] as $label => $key)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $label }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) ($data['investing_activities'][$key] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t border-border font-semibold">
                        <td class="px-4 py-3 text-sm">Net Cash from Investing</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $data['investing_total'], 2) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Financing Activities">
            <x-table>
                <x-slot:tbody>
                    @foreach ([
                        'Debt Issued' => 'debt_issued',
                        'Equity Issued' => 'equity_issued',
                        'Dividends Paid' => 'dividends',
                    ] as $label => $key)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $label }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) ($data['financing_activities'][$key] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t border-border font-semibold">
                        <td class="px-4 py-3 text-sm">Net Cash from Financing</td>
                        <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $data['financing_total'], 2) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card>
            <div class="flex items-center justify-between">
                <span class="text-base font-semibold text-ink">Net Change in Cash</span>
                <span class="text-base font-semibold font-mono {{ (float) $data['net_change_in_cash'] < 0 ? 'text-danger' : 'text-success' }}">
                    RM {{ number_format((float) $data['net_change_in_cash'], 2) }}
                </span>
            </div>
        </x-card>
    </div>
</x-app-layout>
