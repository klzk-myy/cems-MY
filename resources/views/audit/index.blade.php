@extends('layouts.base')

@section('title', 'Audit Log')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Audit Log</h1>
    <p class="text-sm text-[--color-ink-muted]">System activity and security events</p>
</div>
@endsection

@section('content')
{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Action</label>
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    @foreach($actions ?? [] as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Severity</label>
                <select name="severity" class="form-select">
                    <option value="">All Severities</option>
                    @foreach($severities ?? [] as $sev)
                        <option value="{{ $sev }}" {{ request('severity') === $sev ? 'selected' : '' }}>{{ $sev }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">User</label>
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    @foreach($users ?? [] as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->username }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-input" value="{{ request('date_from') }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-input" value="{{ request('date_to') }}">
            </div>
            <div class="md:col-span-5 flex justify-end gap-2">
                <a href="{{ route('audit.index') }}" class="btn btn-secondary">Clear</a>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

{{-- Audit Log Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Severity</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>IP Address</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs ?? [] as $log)
                <tr>
                    <td class="font-mono text-xs text-[--color-ink-muted] whitespace-nowrap">
                        {{ $log->created_at->format('d M Y, H:i:s') }}
                    </td>
                    <td>
                        @php $severityClass = match($log->severity) { 'CRITICAL' => 'danger', 'ERROR' => 'danger', 'WARNING' => 'warning', default => 'info' }; @endphp
                        <span class="badge badge-{{ $severityClass }}">{{ $log->severity ?? 'INFO' }}</span>
                    </td>
                    <td>
                        <span class="text-sm font-medium">{{ $log->action ?? 'N/A' }}</span>
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-[--color-canvas-subtle] rounded flex items-center justify-center text-xs">
                                {{ substr($log->user->username ?? 'S', 0, 1) }}
                            </div>
                            <span class="text-sm">{{ $log->user->username ?? 'System' }}</span>
                        </div>
                    </td>
                    <td class="font-mono text-xs">{{ $log->ip_address ?? 'N/A' }}</td>
                    <td class="text-sm max-w-xs truncate" title="{{ $log->description ?? '' }}">{{ $log->description ?? 'No description' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No audit logs found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs && $logs->hasPages())
        <div class="card-footer">
            {{ $logs->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
