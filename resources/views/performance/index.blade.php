<x-app-layout title="Performance Monitoring">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header title="Performance Monitoring" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Cache Statistics">
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-3 border-b border-border">
                        <span class="text-sm text-ink-muted">Cache Driver</span>
                        <span class="text-sm font-medium text-ink">{{ $metrics['cache_stats']['driver'] ?? 'Unknown' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-border">
                        <span class="text-sm text-ink-muted">Hit Rate</span>
                        <span class="text-sm font-medium text-ink">{{ $metrics['cache_stats']['hit_rate'] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-border">
                        <span class="text-sm text-ink-muted">Total Keys</span>
                        <span class="text-sm font-medium text-ink">{{ $metrics['cache_stats']['total_keys'] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-sm text-ink-muted">Memory Usage</span>
                        <span class="text-sm font-medium text-ink">{{ $metrics['cache_stats']['memory_usage'] ?? 'N/A' }}</span>
                    </div>
                </div>
            </x-card>

            <x-card title="Query Statistics">
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-3 border-b border-border">
                        <span class="text-sm text-ink-muted">Total Queries</span>
                        <x-badge variant="info">{{ number_format($metrics['query_count']) }}</x-badge>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-border">
                        <span class="text-sm text-ink-muted">Slow Queries</span>
                        <x-badge :variant="$metrics['slow_query_count'] > 0 ? 'warning' : 'success'">
                            {{ number_format($metrics['slow_query_count']) }}
                        </x-badge>
                    </div>
                    <div class="flex justify-between items-center py-3 border-b border-border">
                        <span class="text-sm text-ink-muted">N+1 Queries</span>
                        <x-badge :variant="$metrics['n_plus_one_count'] > 0 ? 'danger' : 'success'">
                            {{ number_format($metrics['n_plus_one_count']) }}
                        </x-badge>
                    </div>
                    <div class="flex justify-between items-center py-3">
                        <span class="text-sm text-ink-muted">Total Query Time</span>
                        <span class="text-sm font-medium text-ink">{{ number_format($metrics['total_query_time_ms'], 2) }} ms</span>
                    </div>
                </div>
            </x-card>
        </div>

        <x-card title="System Health">
            <x-stat-grid cols="4">
                <x-stat-card label="Total Queries" :value="number_format($metrics['query_count'])" />
                <x-stat-card
                    label="Slow Queries"
                    :value="$metrics['slow_query_count']"
                    :color="$metrics['slow_query_count'] > 0 ? 'yellow' : 'green'"
                />
                <x-stat-card
                    label="N+1 Queries"
                    :value="$metrics['n_plus_one_count']"
                    :color="$metrics['n_plus_one_count'] > 0 ? 'red' : 'green'"
                />
                <x-stat-card label="Total Time" :value="number_format($metrics['total_query_time_ms'], 0) . 'ms'" />
            </x-stat-grid>
        </x-card>
    </div>
</x-app-layout>
