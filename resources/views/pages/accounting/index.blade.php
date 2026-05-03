<x-app-layout title="Accounting">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Accounting Dashboard</h1>

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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-2">Trial Balance</h3>
                <a href="{{ route('accounting.trial-balance') }}" class="text-blue-600 hover:underline">View Report</a>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-2">Profit & Loss</h3>
                <a href="{{ route('accounting.profit-loss') }}" class="text-blue-600 hover:underline">View Report</a>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-2">Balance Sheet</h3>
                <a href="{{ route('accounting.balance-sheet') }}" class="text-blue-600 hover:underline">View Report</a>
            </div>
        </div>
    </div>
</x-app-layout>