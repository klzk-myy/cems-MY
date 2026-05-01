@extends('layouts.base')

@section('title', 'CTOS Reports - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">CTOS Reports</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Credit check reports integration</p>
    </div>
    <a href="{{ route('compliance.ctos.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Inquiry
    </a>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">CTOS Inquiries</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Report Type</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ctosReports ?? [] as $report)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="font-mono text-xs text-[--color-ink]">{{ $report->id }}</td>
                    <td class="text-[--color-ink]">{{ $report->customer->full_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">{{ $report->report_type ?? 'Standard' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($report->status === 'completed') bg-green-100 text-green-700
                            @elseif($report->status === 'pending') bg-yellow-100 text-yellow-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ ucfirst($report->status ?? 'pending') }}
                        </span>
                    </td>
                    <td class="text-[--color-ink] text-sm">{{ $report->created_at->format('Y-m-d') }}</td>
                    <td class="text-[--color-ink]">
                        <a href="{{ route('compliance.ctos.show', $report) }}" class="text-[--color-accent] hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-[--color-ink-muted]">No CTOS reports found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection