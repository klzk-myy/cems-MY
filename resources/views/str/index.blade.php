@extends('layouts.base')

@section('title', 'STR Reports - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">STR Reports</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Suspicious Transaction Reports management</p>
    </div>
    <a href="{{ route('str.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Create STR
    </a>
</div>

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Draft</div>
        <div class="text-2xl font-bold text-[--color-ink]">{{ $stats['draft'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Pending Review</div>
        <div class="text-2xl font-bold text-yellow-600">{{ $stats['pending_review'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Pending Approval</div>
        <div class="text-2xl font-bold text-orange-600">{{ $stats['pending_approval'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Submitted</div>
        <div class="text-2xl font-bold text-blue-600">{{ $stats['submitted'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-sm text-[--color-ink-muted]">Acknowledged</div>
        <div class="text-2xl font-bold text-green-600">{{ $stats['acknowledged'] }}</div>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All STR Reports</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Ref No</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($strReports as $str)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-mono text-sm">{{ $str->reference_number ?? 'DRAFT' }}</td>
                    <td class="text-[--color-ink]">{{ $str->customer->full_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($str->status->value === 'draft') bg-gray-100 text-gray-700
                            @elseif($str->status->value === 'pending_review') bg-yellow-100 text-yellow-700
                            @elseif($str->status->value === 'pending_approval') bg-orange-100 text-orange-700
                            @elseif($str->status->value === 'submitted') bg-blue-100 text-blue-700
                            @else bg-green-100 text-green-700
                            @endif">
                            {{ $str->status->label() }}
                        </span>
                    </td>
                    <td class="text-[--color-ink] text-sm">{{ $str->created_at->format('Y-m-d') }}</td>
                    <td class="text-[--color-ink] text-sm">{{ $str->submitted_at?->format('Y-m-d') ?? '-' }}</td>
                    <td class="text-[--color-ink]">
                        <a href="{{ route('str.show', $str) }}" class="text-[--color-accent] hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-[--color-ink-muted]">No STR reports found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($strReports->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $strReports->links() }}
    </div>
    @endif
</div>
@endsection