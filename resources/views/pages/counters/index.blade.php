<x-app-layout title="Counters">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Counters</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Total Counters</div>
                <div class="text-2xl font-bold">{{ $stats['total'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Open</div>
                <div class="text-2xl font-bold text-green-600">{{ $stats['open'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Available</div>
                <div class="text-2xl font-bold text-blue-600">{{ $stats['available'] ?? 0 }}</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Counter</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($counters as $counter)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $counter->name }}</td>
                        <td class="px-4 py-3">
                            @if($counter->sessions->count() > 0)
                                <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Open</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-800">Available</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($counter->sessions->count() === 0)
                                <a href="{{ route('counters.open', $counter) }}" class="text-blue-600 hover:underline">Open</a>
                            @else
                                <a href="{{ route('counters.history', $counter) }}" class="text-gray-600 hover:underline">History</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-gray-500">No counters found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>