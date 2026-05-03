<x-app-layout title="Stock & Cash">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Stock & Cash Positions</h1>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Currency Positions</h2>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-2">Currency</th>
                        <th class="px-4 py-2">Available</th>
                        <th class="px-4 py-2">Held</th>
                        <th class="px-4 py-2">Total</th>
                        <th class="px-4 py-2">P&L</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($positions ?? [] as $position)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $position->currency_code }}</td>
                        <td class="px-4 py-2">{{ number_format($position->available, 2) }}</td>
                        <td class="px-4 py-2">{{ number_format($position->held, 2) }}</td>
                        <td class="px-4 py-2">{{ number_format($position->total, 2) }}</td>
                        <td class="px-4 py-2 {{ $position->pnl >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($position->pnl, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-gray-500">No positions found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Today's Till Summary</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Open Tills</dt>
                        <dd class="font-medium">{{ count($openTills ?? []) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Closed Tills</dt>
                        <dd class="font-medium">{{ count($closedTills ?? []) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Variance</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Total Variance</dt>
                        <dd class="font-medium {{ $totalPnl >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($totalPnl ?? 0, 2) }} MYR
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>