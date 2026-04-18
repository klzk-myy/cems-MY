@extends('layouts.base')

@section('title', 'STR Reports')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Suspicious Transaction Reports</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage and submit STRs to BNM</p>
</div>
@endsection

@section('header-actions')
<a href="/str/create" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
    </svg>
    New STR
</a>
@endsection

@section('content')
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>STR ID</th>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Submitted Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($strReports ?? [] as $str)
                <tr>
                    <td class="font-mono text-xs">#{{ $str->id }}</td>
                    <td class="font-mono">{{ $str->reference_number ?? 'DRAFT' }}</td>
                    <td>
                        @if($str->customer)
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center text-xs">
                                {{ substr($str->customer->full_name, 0, 1) }}
                            </div>
                            <span class="font-medium">{{ $str->customer->full_name }}</span>
                        </div>
                        @else
                        <span class="text-[--color-ink-muted]">N/A</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusClass = match($str->status->value ?? '') {
                                'Submitted' => 'badge-success',
                                'Approved' => 'badge-info',
                                'Draft' => 'badge-warning',
                                'Rejected' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $str->status->label() ?? 'Draft' }}</span>
                    </td>
                    <td class="text-[--color-ink-muted]">
                        {{ $str->submitted_at?->format('d M Y') ?? 'Not submitted' }}
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="/str/{{ $str->id }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            @if($str->status->value === 'Draft')
                                <a href="/str/{{ $str->id }}/edit" class="btn btn-ghost btn-icon" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No STRs found</p>
                            <p class="empty-state-description">Create a new STR when suspicious activity is detected</p>
                            <a href="/str/create" class="btn btn-primary mt-4">New STR</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($strReports && $strReports->hasPages())
        <div class="card-footer">
            {{ $strReports->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
