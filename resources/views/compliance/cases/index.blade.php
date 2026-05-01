@extends('layouts.base')

@section('title', 'Cases - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Cases</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Compliance case management</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
        <div class="text-2xl font-bold text-red-600">{{ $summary['critical'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Critical</div>
    </div>
    <div class="card p-4">
        <div class="text-2xl font-bold text-orange-600">{{ $summary['high'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">High</div>
    </div>
    <div class="card p-4">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $summary['open'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Open</div>
    </div>
    <div class="card p-4">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $summary['resolved'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Resolved</div>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Cases</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>SLA Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cases as $case)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="font-mono text-xs text-[--color-ink]">{{ $case->id }}</td>
                    <td class="text-[--color-ink]">{{ $case->customer->full_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($case->priority === 'critical') bg-red-100 text-red-700
                            @elseif($case->priority === 'high') bg-orange-100 text-orange-700
                            @elseif($case->priority === 'medium') bg-yellow-100 text-yellow-700
                            @else bg-blue-100 text-blue-700
                            @endif">
                            {{ ucfirst($case->priority) }}
                        </span>
                    </td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($case->status === 'open') bg-yellow-100 text-yellow-700
                            @elseif($case->status === 'in_progress') bg-blue-100 text-blue-700
                            @elseif($case->status === 'pending_review') bg-orange-100 text-orange-700
                            @else bg-green-100 text-green-700
                            @endif">
                            {{ str_replace('_', ' ', ucfirst($case->status)) }}
                        </span>
                    </td>
                    <td class="text-[--color-ink]">{{ $case->assignedTo?->username ?? 'Unassigned' }}</td>
                    <td class="text-[--color-ink] text-sm">{{ $case->sla_deadline?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td class="text-[--color-ink]">
                        <a href="{{ route('compliance.cases.show', $case) }}" class="text-[--color-accent] hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-[--color-ink-muted]">No cases found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($cases->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $cases->links() }}
    </div>
    @endif
</div>
@endsection