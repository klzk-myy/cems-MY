@extends('layouts.base')

@section('title', 'Audit Logs - CEMS-MY')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Audit Logs</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">System activity and change tracking</p>
    </div>
</div>

<div class="card mb-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Filters</h3>
    </div>
    <div class="p-6">
        <form method="GET" action="{{ route('audit.index') }}" class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <label class="text-sm text-[--color-ink-muted] whitespace-nowrap">Date:</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    class="px-3 py-2 border border-[--color-border] rounded-lg text-sm w-36">
                <span class="text-[--color-ink-muted]">to</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    class="px-3 py-2 border border-[--color-border] rounded-lg text-sm w-36">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-[--color-ink-muted] whitespace-nowrap">User:</label>
                <select name="user_id" class="px-3 py-2 border border-[--color-border] rounded-lg text-sm w-40">
                    <option value="">All</option>
                    @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ ($filters['user_id'] ?? '') == $user->id ? 'selected' : '' }}>
                        {{ $user->username }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-[--color-ink-muted] whitespace-nowrap">Action:</label>
                <select name="action" class="px-3 py-2 border border-[--color-border] rounded-lg text-sm w-40">
                    <option value="">All</option>
                    @foreach($actions as $action)
                    <option value="{{ $action }}" {{ ($filters['action'] ?? '') == $action ? 'selected' : '' }}>
                        {{ $action }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-[--color-ink-muted] whitespace-nowrap">Severity:</label>
                <select name="severity" class="px-3 py-2 border border-[--color-border] rounded-lg text-sm w-32">
                    <option value="">All</option>
                    @foreach($severities as $severity)
                    <option value="{{ $severity }}" {{ ($filters['severity'] ?? '') == $severity ? 'selected' : '' }}>
                        {{ $severity }}
                    </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                Filter
            </button>
            <a href="{{ route('audit.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
                Clear
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Severity</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] text-xs font-mono">
                        {{ $log->created_at->format('Y-m-d H:i:s') }}
                    </td>
                    <td class="text-[--color-ink]">
                        {{ $log->user->username ?? 'System' }}
                    </td>
                    <td class="text-[--color-ink]">
                        <span class="font-medium">{{ $log->action }}</span>
                    </td>
                    <td class="text-[--color-ink] text-xs">
                        @if($log->entity_type)
                        <span class="text-[--color-ink-muted]">{{ $log->entity_type }}</span>
                        @if($log->entity_id)
                        <span class="text-[--color-ink-muted]"> #{{ $log->entity_id }}</span>
                        @endif
                        @else
                        -
                        @endif
                    </td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($log->severity === 'CRITICAL') bg-red-100 text-red-700
                            @elseif($log->severity === 'ERROR') bg-orange-100 text-orange-700
                            @elseif($log->severity === 'WARNING') bg-yellow-100 text-yellow-700
                            @else bg-blue-100 text-blue-700
                            @endif">
                            {{ $log->severity ?? 'INFO' }}
                        </span>
                    </td>
                    <td class="text-[--color-ink] text-xs font-mono text-[--color-ink-muted]">
                        {{ $log->ip_address ?? '-' }}
                    </td>
                    <td class="text-[--color-ink]">
                        @if($log->old_values || $log->new_values)
                        <button type="button" class="text-[--color-accent] hover:underline text-xs"
                            onclick="toggleLogDetails({{ $log->id }})">
                            View
                        </button>
                        <div id="log-details-{{ $log->id }}" class="hidden mt-2 p-3 bg-[--color-canvas-subtle] rounded text-xs font-mono">
                            @if($log->old_values)
                            <div class="mb-2"><strong>Old:</strong> {{ json_encode($log->old_values) }}</div>
                            @endif
                            @if($log->new_values)
                            <div><strong>New:</strong> {{ json_encode($log->new_values) }}</div>
                            @endif
                        </div>
                        @else
                        <span class="text-[--color-ink-muted] text-xs">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-[--color-ink-muted]">No audit logs found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $logs->links() }}
    </div>
    @endif
</div>

<script>
function toggleLogDetails(logId) {
    const details = document.getElementById('log-details-' + logId);
    if (details.classList.contains('hidden')) {
        details.classList.remove('hidden');
    } else {
        details.classList.add('hidden');
    }
}
</script>
@endsection