<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Balance Sheet</h1>
            <select wire:model="asOfDate" class="rounded border border-[var(--color-border)] px-3 py-2">
                @foreach($periods as $period)
                <option value="{{ $period->end_date }}">{{ $period->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Assets</h2>
            <table class="w-full mb-6">
                <tbody>
                    @foreach($assets as $asset)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 pl-8">{{ $asset->name }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($asset->balance, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-bold border-t-2 border-[var(--color-border)]">
                        <td class="px-4 py-2">Total Assets</td>
                        <td class="px-4 py-2 text-right">${{ number_format($totalAssets, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Liabilities</h2>
            <table class="w-full mb-6">
                <tbody>
                    @foreach($liabilities as $liability)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 pl-8">{{ $liability->name }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($liability->balance, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-bold border-t-2 border-[var(--color-border)]">
                        <td class="px-4 py-2">Total Liabilities</td>
                        <td class="px-4 py-2 text-right">${{ number_format($totalLiabilities, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Equity</h2>
            <table class="w-full">
                <tbody>
                    @foreach($equity as $eq)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 pl-8">{{ $eq->name }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($eq->balance, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-bold border-t-2 border-[var(--color-border)]">
                        <td class="px-4 py-2">Total Equity</td>
                        <td class="px-4 py-2 text-right">${{ number_format($totalEquity, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-6 p-4 bg-gray-100 rounded font-bold">
                <div class="flex justify-between">
                    <span>Total Liabilities + Equity</span>
                    <span>${{ number_format($totalLiabilities + $totalEquity, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>