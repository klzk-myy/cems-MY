@extends('layouts.base')

@section('title', 'Performance Monitoring - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Performance Monitoring</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Cache, queries, and system performance metrics</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="card">
        <div class="text-sm text-[--color-ink-muted] mb-2">Query Count</div>
        <div class="text-3xl font-bold text-[--color-ink]">{{ $metrics['query_count'] }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">Total queries this request</div>
    </div>
    <div class="card">
        <div class="text-sm text-[--color-ink-muted] mb-2">Slow Queries</div>
        <div class="text-3xl font-bold text-orange-600">{{ $metrics['slow_query_count'] }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">&gt;100ms execution time</div>
    </div>
    <div class="card">
        <div class="text-sm text-[--color-ink-muted] mb-2">N+1 Detected</div>
        <div class="text-3xl font-bold text-red-600">{{ $metrics['n_plus_one_count'] }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">Repeated query patterns</div>
    </div>
    <div class="card">
        <div class="text-sm text-[--color-ink-muted] mb-2">Total Query Time</div>
        <div class="text-3xl font-bold text-[--color-ink]">{{ number_format($metrics['total_query_time_ms'] ?? 0, 2) }}ms</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">Combined execution time</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Cache Performance</h3>
        </div>
        <div class="p-6">
            @php
            $cacheStats = $metrics['cache_stats'] ?? [];
            $hitRate = $cacheStats['hit_rate'] ?? 0;
            @endphp
            <div class="mb-6">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-[--color-ink-muted]">Hit Rate</span>
                    <span class="text-lg font-semibold text-[--color-ink]">{{ number_format($hitRate, 1) }}%</span>
                </div>
                <div class="w-full bg-[--color-canvas-subtle] rounded-full h-3">
                    <div class="h-3 rounded-full {{ $hitRate >= 80 ? 'bg-green-500' : ($hitRate >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                        style="width: {{ $hitRate }}%"></div>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-sm text-[--color-ink-muted]">Memory Usage</span>
                    <span class="text-sm font-medium text-[--color-ink]">
                        {{ $cacheStats['memory_usage']['used_memory_human'] ?? 'N/A' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-[--color-ink-muted]">Peak Memory</span>
                    <span class="text-sm font-medium text-[--color-ink]">
                        {{ $cacheStats['memory_usage']['used_memory_peak_human'] ?? $cacheStats['memory_usage']['used_memory_human'] ?? 'N/A' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-[--color-ink-muted]">Total Keys</span>
                    <span class="text-sm font-medium text-[--color-ink]">
                        {{ number_format($cacheStats['keys_count'] ?? 0) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Query Health</h3>
        </div>
        <div class="p-6">
            @php
            $queryCount = $metrics['query_count'] ?? 0;
            $slowCount = $metrics['slow_query_count'] ?? 0;
            $nPlusOne = $metrics['n_plus_one_count'] ?? 0;
            @endphp
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 rounded-lg {{ $queryCount == 0 ? 'bg-green-50' : ($queryCount <= 20 ? 'bg-blue-50' : 'bg-yellow-50') }}">
                    <div>
                        <div class="text-sm font-medium text-[--color-ink]">Query Volume</div>
                        <div class="text-xs text-[--color-ink-muted]">
                            @if($queryCount == 0)
                                No queries detected
                            @elseif($queryCount <= 20)
                                Healthy
                            @elseif($queryCount <= 50)
                                Moderate
                            @else
                                High - review for optimization
                            @endif
                        </div>
                    </div>
                    <div class="text-2xl font-bold {{ $queryCount == 0 ? 'text-green-600' : ($queryCount <= 20 ? 'text-blue-600' : ($queryCount <= 50 ? 'text-yellow-600' : 'text-orange-600')) }}">
                        {{ $queryCount }}
                    </div>
                </div>
                <div class="flex items-center justify-between p-4 rounded-lg {{ $slowCount == 0 ? 'bg-green-50' : ($slowCount <= 3 ? 'bg-yellow-50' : 'bg-red-50') }}">
                    <div>
                        <div class="text-sm font-medium text-[--color-ink]">Slow Queries</div>
                        <div class="text-xs text-[--color-ink-muted]">
                            @if($slowCount == 0)
                                No slow queries
                            @elseif($slowCount <= 3)
                                Acceptable
                            @else
                                Needs attention
                            @endif
                        </div>
                    </div>
                    <div class="text-2xl font-bold {{ $slowCount == 0 ? 'text-green-600' : ($slowCount <= 3 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $slowCount }}
                    </div>
                </div>
                <div class="flex items-center justify-between p-4 rounded-lg {{ $nPlusOne == 0 ? 'bg-green-50' : ($nPlusOne <= 2 ? 'bg-yellow-50' : 'bg-red-50') }}">
                    <div>
                        <div class="text-sm font-medium text-[--color-ink]">N+1 Patterns</div>
                        <div class="text-xs text-[--color-ink-muted]">
                            @if($nPlusOne == 0)
                                No N+1 detected
                            @elseif($nPlusOne <= 2)
                                Minor issues
                            @else
                                Major issues - fix relationships
                            @endif
                        </div>
                    </div>
                    <div class="text-2xl font-bold {{ $nPlusOne == 0 ? 'text-green-600' : ($nPlusOne <= 2 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $nPlusOne }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 p-4 bg-[--color-canvas-subtle] rounded-lg">
    <h4 class="text-sm font-medium text-[--color-ink] mb-2">Performance Tips</h4>
    <ul class="text-xs text-[--color-ink-muted] space-y-1">
        <li>• Keep query count under 20 per page to maintain fast load times</li>
        <li>• Use eager loading (with()) to prevent N+1 query problems</li>
        <li>• Cache frequently accessed data that doesn't change often</li>
        <li>• Aim for cache hit rate above 80% for optimal performance</li>
    </ul>
</div>
@endsection