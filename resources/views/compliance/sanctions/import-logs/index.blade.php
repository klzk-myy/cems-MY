@extends('layouts.base')

@section('title', 'Import Logs')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Sanction Import Logs</h1>
    <p class="text-sm text-[--color-ink-muted]">History of sanction list imports</p>
</div>
@endsection

@section('header-actions')
<a href="/compliance/sanctions" class="btn btn-ghost">Back to Lists</a>
@endsection

@section('content')
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>List</th>
                    <th>Imported At</th>
                    <th>Added</th>
                    <th>Updated</th>
                    <th>Deactivated</th>
                    <th>Status</th>
                    <th>Triggered By</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="font-medium">{{ $log['list_name'] ?? 'N/A' }}</td>
                    <td>{{ isset($log['imported_at']) ? \Carbon\Carbon::parse($log['imported_at'])->format('d M Y H:i') : 'N/A' }}</td>
                    <td>
                        <span class="badge badge-success">{{ $log['records_added'] ?? 0 }}</span>
                    </td>
                    <td>
                        <span class="badge badge-info">{{ $log['records_updated'] ?? 0 }}</span>
                    </td>
                    <td>
                        <span class="badge badge-warning">{{ $log['records_deactivated'] ?? 0 }}</span>
                    </td>
                    <td>
                        @if(($log['status'] ?? '') === 'success')
                            <span class="badge badge-success">Success</span>
                        @else
                            <span class="badge badge-danger">Failed</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-default">{{ ucfirst($log['triggered_by'] ?? 'N/A') }}</span>
                    </td>
                    <td class="text-red-600 text-sm max-w-xs truncate">
                        {{ $log['error_message'] ?? '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-12 text-[--color-ink-muted]">No import logs found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
