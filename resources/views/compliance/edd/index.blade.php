@extends('layouts.base')

@section('title', 'EDD Records')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Enhanced Due Diligence</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage EDD questionnaires and records</p>
</div>
@endsection

@section('content')
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Risk Level</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records ?? [] as $record)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center text-xs">
                                {{ substr($record->customer->full_name ?? '?', 0, 1) }}
                            </div>
                            <span class="font-medium">{{ $record->customer->full_name ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td>
                        @php $riskClass = match($record->risk_level ?? '') { 'High' => 'badge-danger', 'Medium' => 'badge-warning', default => 'badge-info' }; @endphp
                        <span class="badge {{ $riskClass }}">{{ $record->risk_level ?? 'N/A' }}</span>
                    </td>
                    <td>
                        @php $statusClass = match($record->status->value ?? '') { 'Approved' => 'badge-success', 'Rejected' => 'badge-danger', default => 'badge-warning' }; @endphp
                        <span class="badge {{ $statusClass }}">{{ $record->status->label() ?? 'Pending' }}</span>
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $record->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="/compliance/edd/{{ $record->id }}" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-12 text-[--color-ink-muted]">No EDD records found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
