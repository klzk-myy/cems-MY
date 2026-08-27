<x-app-layout title="Exchange Rates">
    <x-page-header title="Exchange Rates" description="View and manage currency rates" />

    <x-card>
        <form id="override-form" method="POST" action="{{ route('rates.override') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-select name="currency_code" label="Currency" :options="['USD' => 'USD', 'EUR' => 'EUR', 'SGD' => 'SGD']" :required="true" />
                <x-input name="rate_buy" label="Buy Rate" type="number" :required="true" />
                <x-input name="rate_sell" label="Sell Rate" type="number" :required="true" />
            </div>
            <x-textarea name="reason" label="Reason for Override" :required="true" />
            <div class="flex justify-end">
                <x-button type="submit" variant="primary">Override Rates</x-button>
            </div>
        </form>
    </x-card>

    <div class="mt-6">
        <x-card title="Current Rates">
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3">Currency</th>
                        <th class="px-4 py-3">Buy Rate</th>
                        <th class="px-4 py-3">Sell Rate</th>
                        <th class="px-4 py-3">Updated</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink-muted" colspan="4">
                            <x-empty-state title="No rates available" description="Rates will appear here once configured." />
                        </td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
