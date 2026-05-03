<x-app-layout title="Stock Transfers">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Stock Transfers</h1>
            @can('role:manager')
            <a href="{{ route('stock-transfers.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                New Transfer
            </a>
            @endcan
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">From</th>
                        <th class="px-4 py-3">To</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers ?? [] as $transfer)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-sm">{{ $transfer->reference }}</td>
                        <td class="px-4 py-3">{{ $transfer->source_branch_id }}</td>
                        <td class="px-4 py-3">{{ $transfer->destination_branch_id }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs 
                                @if($transfer->status === 'Completed') bg-green-100 text-green-800
                                @elseif($transfer->status === 'Pending') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ $transfer->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $transfer->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('stock-transfers.show', $transfer) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No transfers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>