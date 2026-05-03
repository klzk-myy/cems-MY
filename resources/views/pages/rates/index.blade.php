<x-app-layout title="Exchange Rates">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Exchange Rates</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Currency</th>
                        <th class="px-4 py-3">Buy Rate</th>
                        <th class="px-4 py-3">Sell Rate</th>
                        <th class="px-4 py-3">Spread</th>
                        <th class="px-4 py-3">Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rates ?? [] as $rate)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $rate->currency_code }}</td>
                        <td class="px-4 py-3">{{ number_format($rate->rate_buy, 4) }}</td>
                        <td class="px-4 py-3">{{ number_format($rate->rate_sell, 4) }}</td>
                        <td class="px-4 py-3">{{ number_format($rate->rate_sell - $rate->rate_buy, 4) }}</td>
                        <td class="px-4 py-3">{{ $rate->updated_at?->format('M d, H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No rates found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>