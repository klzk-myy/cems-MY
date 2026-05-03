<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Stock Position</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-4 gap-6 mb-6">
                @foreach($positions as $position)
                <div class="border border-[var(--color-border)] rounded p-4">
                    <h3 class="font-medium text-[var(--color-ink)]">{{ $position['location'] }}</h3>
                    <p class="text-2xl font-bold mt-2">{{ $position['quantity'] }}</p>
                    <p class="text-sm text-gray-500">items</p>
                </div>
                @endforeach
            </div>

            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Location</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Product</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Quantity</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockPositions as $item)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $item->location }}</td>
                        <td class="px-4 py-3">{{ $item->product_code }}</td>
                        <td class="px-4 py-3 text-right">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($item->value, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-center">No stock positions</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>