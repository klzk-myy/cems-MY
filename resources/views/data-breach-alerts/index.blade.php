@extends('layouts.base')

@section('title', 'Data Breach Alerts')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Data Breach Alerts</h1>
    <p class="text-sm text-[--color-ink-muted]">Security incident monitoring</p>
</div>
@endsection

@section('content')
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Detected</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($alerts ?? [] as $alert)
                <tr>
                    <td class="font-mono">#{{ $alert->id }}</td>
                    <td>{{ $alert->type ?? 'Unknown' }}</td>
                    <td>
                        @php
                            $severityClass = match($alert->severity ?? '') {
                                'Critical' => 'badge-danger',
                                'High' => 'badge-warning',
                                'Medium' => 'badge-info',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $severityClass }}">{{ $alert->severity ?? 'Low' }}</span>
                    </td>
                    <td>
                        @if($alert->is_resolved)
                            <span class="badge badge-success">Resolved</span>
                        @else
                            <span class="badge badge-warning">Active</span>
                        @endif
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $alert->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="/data-breach-alerts/{{ $alert->id }}" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No data breach alerts</p>
                            <p class="empty-state-description">All systems are secure</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
