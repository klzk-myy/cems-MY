<x-app-layout title="Performance Monitoring">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Performance Monitoring</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm mb-1">Avg Response Time</h3>
                <div class="text-2xl font-bold">{{ $metrics['avg_response_time'] ?? 'N/A' }}ms</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm mb-1">Requests/sec</h3>
                <div class="text-2xl font-bold">{{ $metrics['requests_per_second'] ?? 'N/A' }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm mb-1">Error Rate</h3>
                <div class="text-2xl font-bold text-red-600">{{ $metrics['error_rate'] ?? 'N/A' }}%</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Cache Performance</h2>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-2">Cache Key</th>
                        <th class="px-4 py-2">Hits</th>
                        <th class="px-4 py-2">Misses</th>
                        <th class="px-4 py-2">Hit Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cacheStats ?? [] as $key => $stat)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-mono text-sm">{{ $key }}</td>
                        <td class="px-4 py-2">{{ $stat['hits'] ?? 0 }}</td>
                        <td class="px-4 py-2">{{ $stat['misses'] ?? 0 }}</td>
                        <td class="px-4 py-2">{{ $stat['hit_rate'] ?? 'N/A' }}%</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-4 text-center text-gray-500">No cache statistics available.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>