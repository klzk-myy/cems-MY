<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Monitoring - CEMS-MY</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f7f7f8; margin: 0; padding: 20px; }
    </style>
</head>
<body class="font-sans antialiased bg-[#f7f7f8]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">Performance Monitoring</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="text-sm font-medium text-gray-500 mb-1">Cache Hit Rate</div>
                <div class="text-3xl font-bold text-gray-900">{{ number_format($metrics['cache_stats']['hit_rate'], 1) }}%</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="text-sm font-medium text-gray-500 mb-1">Cache Keys</div>
                <div class="text-3xl font-bold text-gray-900">{{ number_format($metrics['cache_stats']['keys_count']) }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="text-sm font-medium text-gray-500 mb-1">Query Count</div>
                <div class="text-3xl font-bold text-gray-900">{{ number_format($metrics['query_count']) }}</div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Cache Statistics</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <dt class="text-sm text-gray-500">Memory Used</dt>
                    <dd class="text-lg font-medium text-gray-900">{{ $metrics['cache_stats']['memory_usage']['used_memory_human'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Peak Memory</dt>
                    <dd class="text-lg font-medium text-gray-900">{{ $metrics['cache_stats']['memory_usage']['used_memory_peak'] > 0 ? number_format($metrics['cache_stats']['memory_usage']['used_memory_peak'] / 1024 / 1024, 2) . ' MB' : 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Total Keys</dt>
                    <dd class="text-lg font-medium text-gray-900">{{ number_format($metrics['cache_stats']['keys_count']) }}</dd>
                </div>
            </dl>
        </div>

        @if(!empty($metrics['queries']))
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Queries</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 px-3 font-medium text-gray-500">Query</th>
                            <th class="text-right py-2 px-3 font-medium text-gray-500">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($metrics['queries'], -10) as $query)
                        <tr class="border-b border-gray-100">
                            <td class="py-2 px-3 text-gray-700 font-mono text-xs max-w-md truncate">{{ $query['query'] }}</td>
                            <td class="py-2 px-3 text-gray-500 text-right">{{ isset($query['time']) ? number_format($query['time'], 2) . ' ms' : 'N/A' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</body>
</html>