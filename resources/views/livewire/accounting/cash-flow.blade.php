<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Cash Flow Statement</h1>
            <select wire:model="period" class="rounded border border-[var(--color-border)] px-3 py-2">
                @foreach($periods as $p)
                <option value="{{ $p->start_date }}_{{ $p->end_date }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Operating Activities</h2>
            <table class="w-full mb-6">
                <tbody>
                    @foreach($operatingItems as $item)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 pl-8">{{ $item['name'] }}</td>
                        <td class="px-4 py-2 text-right {{ $item['amount'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $item['amount'] >= 0 ? '$'.number_format($item['amount'], 2) : '($'.number_format(abs($item['amount']), 2).')' }}
                        </td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-bold border-t-2 border-[var(--color-border)]">
                        <td class="px-4 py-2">Net Cash from Operating</td>
                        <td class="px-4 py-2 text-right">${{ number_format($netOperating, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Investing Activities</h2>
            <table class="w-full mb-6">
                <tbody>
                    @foreach($investingItems as $item)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 pl-8">{{ $item['name'] }}</td>
                        <td class="px-4 py-2 text-right {{ $item['amount'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $item['amount'] >= 0 ? '$'.number_format($item['amount'], 2) : '($'.number_format(abs($item['amount']), 2).')' }}
                        </td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-bold border-t-2 border-[var(--color-border)]">
                        <td class="px-4 py-2">Net Cash from Investing</td>
                        <td class="px-4 py-2 text-right">${{ number_format($netInvesting, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Financing Activities</h2>
            <table class="w-full mb-6">
                <tbody>
                    @foreach($financingItems as $item)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 pl-8">{{ $item['name'] }}</td>
                        <td class="px-4 py-2 text-right {{ $item['amount'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $item['amount'] >= 0 ? '$'.number_format($item['amount'], 2) : '($'.number_format(abs($item['amount']), 2).')' }}
                        </td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-bold border-t-2 border-[var(--color-border)]">
                        <td class="px-4 py-2">Net Cash from Financing</td>
                        <td class="px-4 py-2 text-right">${{ number_format($netFinancing, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-6 p-4 bg-[var(--color-ink)] text-white rounded font-bold">
                <div class="flex justify-between text-xl">
                    <span>Net Cash Flow</span>
                    <span>${{ number_format($netCashFlow, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>