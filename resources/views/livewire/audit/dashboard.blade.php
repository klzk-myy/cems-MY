@extends('layouts.base')

@section('title', 'Audit Dashboard')

@section('content')
<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Audit Dashboard</h2>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total Logs</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_logs']) }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Today's Activity</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['today_logs']) }}</div>
                </div>
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="text-sm text-red-500">Critical Issues</div>
                    <div class="text-2xl font-bold text-red-600 mt-1">{{ number_format($stats['critical_logs']) }}</div>
                </div>
                <div class="bg-orange-50 rounded-lg p-4">
                    <div class="text-sm text-orange-500">Errors</div>
                    <div class="text-2xl font-bold text-orange-600 mt-1">{{ number_format($stats['error_logs']) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Severity Distribution</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        @foreach($severityCounts as $severity => $count)
                            <div class="flex items-center justify-between py-2 border-b border-gray-200 last:border-0">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    @if($severity === 'CRITICAL') bg-red-100 text-red-800
                                    @elseif($severity === 'ERROR') bg-orange-100 text-orange-800
                                    @elseif($severity === 'WARNING') bg-yellow-100 text-yellow-800
                                    @else bg-blue-100 text-blue-800 @endif">
                                    {{ $severity }}
                                </span>
                                <span class="text-gray-900 font-medium">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Top Actions</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        @foreach($topActions as $action => $count)
                            <div class="flex items-center justify-between py-2 border-b border-gray-200 last:border-0">
                                <span class="text-gray-700">{{ $action }}</span>
                                <span class="text-gray-900 font-medium">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Recent Activity</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($recentLogs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log['created_at'] }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if($log['severity'] === 'CRITICAL') bg-red-100 text-red-800
                                            @elseif($log['severity'] === 'ERROR') bg-orange-100 text-orange-800
                                            @elseif($log['severity'] === 'WARNING') bg-yellow-100 text-yellow-800
                                            @else bg-blue-100 text-blue-800 @endif">
                                            {{ $log['action'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $log['user_name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $log['description'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">No recent activity</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection