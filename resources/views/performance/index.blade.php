<x-app-layout title="Performance Monitoring">
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-semibold text-gray-900">Performance Monitoring</h1>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Cache Statistics</h2>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-sm text-gray-600">Cache Driver</span>
                                <span class="text-sm font-medium text-gray-900">{{ $metrics['cache_stats']['driver'] ?? 'Unknown' }}</span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-sm text-gray-600">Hit Rate</span>
                                <span class="text-sm font-medium text-gray-900">{{ $metrics['cache_stats']['hit_rate'] ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-sm text-gray-600">Total Keys</span>
                                <span class="text-sm font-medium text-gray-900">{{ $metrics['cache_stats']['total_keys'] ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between items-center py-3">
                                <span class="text-sm text-gray-600">Memory Usage</span>
                                <span class="text-sm font-medium text-gray-900">{{ $metrics['cache_stats']['memory_usage'] ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Query Statistics</h2>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-sm text-gray-600">Total Queries</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                                    {{ number_format($metrics['query_count']) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-sm text-gray-600">Slow Queries</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $metrics['slow_query_count'] > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
                                    {{ number_format($metrics['slow_query_count']) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-sm text-gray-600">N+1 Queries</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $metrics['n_plus_one_count'] > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                    {{ number_format($metrics['n_plus_one_count']) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-3">
                                <span class="text-sm text-gray-600">Total Query Time</span>
                                <span class="text-sm font-medium text-gray-900">{{ number_format($metrics['total_query_time_ms'], 2) }} ms</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">System Health</h2>
                    <div class="grid grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['query_count']) }}</p>
                            <p class="text-sm text-gray-500 mt-1">Total Queries</p>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-semibold {{ $metrics['slow_query_count'] > 0 ? 'text-yellow-600' : 'text-green-600' }}">{{ $metrics['slow_query_count'] }}</p>
                            <p class="text-sm text-gray-500 mt-1">Slow Queries</p>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-semibold {{ $metrics['n_plus_one_count'] > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $metrics['n_plus_one_count'] }}</p>
                            <p class="text-sm text-gray-500 mt-1">N+1 Queries</p>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['total_query_time_ms'], 0) }}ms</p>
                            <p class="text-sm text-gray-500 mt-1">Total Time</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
</x-app-layout>