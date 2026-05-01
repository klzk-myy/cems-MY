@extends('layouts.base')

@section('title', 'Audit Dashboard - CEMS-MY')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Audit Dashboard</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">System activity overview</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Total Logs</div>
        <div class="text-3xl font-bold text-[--color-ink]">{{ $stats['total_logs'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Today</div>
        <div class="text-3xl font-bold text-[--color-ink]">{{ $stats['today_logs'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Critical</div>
        <div class="text-3xl font-bold text-red-600">{{ $stats['critical_logs'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Errors</div>
        <div class="text-3xl font-bold text-orange-600">{{ $stats['error_logs'] }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Severity Distribution</h3>
        </div>
        <div class="p-6">
            @foreach($severityCounts as $severity => $count)
            <div class="flex justify-between items-center py-2 border-b border-[--color-border] last:border-0">
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                    @if($severity === 'CRITICAL') bg-red-100 text-red-700
                    @elseif($severity === 'ERROR') bg-orange-100 text-orange-700
                    @elseif($severity === 'WARNING') bg-yellow-100 text-yellow-700
                    @else bg-blue-100 text-blue-700
                    @endif">
                    {{ $severity }}
                </span>
                <span class="text-lg font-semibold text-[--color-ink]">{{ $count }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Top Actions</h3>
        </div>
        <div class="p-6">
            @foreach($topActions as $action => $count)
            <div class="flex justify-between items-center py-2 border-b border-[--color-border] last:border-0">
                <span class="text-sm text-[--color-ink]">{{ $action }}</span>
                <span class="text-sm font-semibold text-[--color-ink-muted]">{{ $count }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Recent Activity</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Severity</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentLogs as $log)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] text-xs font-mono">{{ $log->created_at->format('H:i:s') }}</td>
                    <td class="text-[--color-ink]">{{ $log->user->username ?? 'System' }}</td>
                    <td class="text-[--color-ink]">{{ $log->action }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($log->severity === 'CRITICAL') bg-red-100 text-red-700
                            @elseif($log->severity === 'ERROR') bg-orange-100 text-orange-700
                            @else bg-blue-100 text-blue-700
                            @endif">
                            {{ $log->severity ?? 'INFO' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-[--color-ink-muted]">No recent activity</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-6 p-6">
    <div class="flex items-center justify-between">
        <div>
            <h4 class="text-sm font-medium text-[--color-ink]">Archive Statistics</h4>
            <p class="text-xs text-[--color-ink-muted]">{{ $archiveStats['total_archived'] ?? 0 }} logs archived</p>
        </div>
        <form action="{{ route('audit.rotate') }}" method="POST">
            @csrf
            <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                Rotate Old Logs
            </button>
        </form>
    </div>
</div>
@endsection