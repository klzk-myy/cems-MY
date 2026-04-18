@extends('layouts.base')

@section('title', 'Audit Dashboard')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Audit Dashboard</h3></div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Total Events (30d)</dt>
                <dd class="text-2xl font-mono">{{ $stats['total_events'] ?? 0 }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Critical Events</dt>
                <dd class="text-2xl font-mono text-red-600">{{ $stats['critical'] ?? 0 }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Chain Integrity</dt>
                <dd class="text-2xl font-mono @if($stats['chain_valid'] ?? true) text-green-600 @else text-red-600 @endif">
                    {{ $stats['chain_valid'] ?? true ? 'Valid' : 'Invalid' }}
                </dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Users Active</dt>
                <dd class="text-2xl font-mono">{{ $stats['active_users'] ?? 0 }}</dd>
            </div>
        </div>

        <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Recent Events</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Severity</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentEvents ?? [] as $event)
                <tr>
                    <td class="font-mono">{{ $event['timestamp'] ?? 'N/A' }}</td>
                    <td>{{ $event['user'] ?? 'N/A' }}</td>
                    <td>{{ $event['action'] ?? 'N/A' }}</td>
                    <td>
                        <span class="badge @if(($event['severity'] ?? '') === 'critical') badge-danger @elseif(($event['severity'] ?? '') === 'warning') badge-warning @else badge-success @endif">
                            {{ $event['severity'] ?? 'info' }}
                        </span>
                    </td>
                    <td class="text-sm">{{ $event['details'] ?? '' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No recent events</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection