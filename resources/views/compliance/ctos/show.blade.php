@extends('layouts.base')

@section('title', $report['ctos_number'] ?? 'CTOS Report')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">CTOS Report</h1>
    <p class="text-sm text-[--color-ink-muted] font-mono">{{ $report['ctos_number'] ?? 'N/A' }}</p>
</div>
@endsection

@section('header-actions')
<div class="flex gap-2">
    @if(($report['status'] ?? '') === 'Draft')
        <form method="POST" action="/compliance/ctos/{{ $report['id'] }}/submit" class="inline">
            @csrf
            <button type="submit" class="btn btn-primary">Submit to BNM</button>
        </form>
    @endif
    <a href="/compliance/ctos" class="btn btn-ghost">Back</a>
</div>
@endsection

@section('content')
<div class="grid grid-cols-2 gap-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Report Information</h3>
            @switch($report['status'] ?? 'Draft')
                @case('Draft')
                    <span class="badge badge-default">Draft</span>
                    @break
                @case('Submitted')
                    <span class="badge badge-info">Submitted</span>
                    @break
                @case('Acknowledged')
                    <span class="badge badge-success">Acknowledged</span>
                    @break
                @case('Rejected')
                    <span class="badge badge-danger">Rejected</span>
                    @break
                @default
                    <span class="badge badge-default">{{ $report['status'] ?? 'Draft' }}</span>
            @endswitch
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">CTOS Number</span>
                    <span class="font-mono font-medium">{{ $report['ctos_number'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Report Date</span>
                    <span class="font-medium">{{ isset($report['report_date']) ? \Carbon\Carbon::parse($report['report_date'])->format('d M Y') : 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Transaction Type</span>
                    @if(($report['transaction_type'] ?? '') === 'Buy')
                        <span class="badge badge-success">Buy</span>
                    @else
                        <span class="badge badge-info">Sell</span>
                    @endif
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Amount (MYR)</span>
                    <span class="font-mono font-bold">RM {{ number_format($report['amount_local'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Foreign Amount</span>
                    <span class="font-mono">{{ number_format($report['amount_foreign'] ?? 0, 2) }} {{ $report['currency_code'] ?? '' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Customer Information</h3>
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Name</span>
                    <span class="font-medium">{{ $report['customer_name'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">ID Type</span>
                    <span class="font-medium">{{ $report['id_type'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">ID Number</span>
                    <span class="font-mono">{{ $report['id_number_masked'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Date of Birth</span>
                    <span class="font-medium">{{ isset($report['date_of_birth']) ? \Carbon\Carbon::parse($report['date_of_birth'])->format('d M Y') : 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Nationality</span>
                    <span class="font-medium">{{ $report['nationality'] ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@if(($report['status'] ?? '') !== 'Draft' && isset($report['bnm_reference']))
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">BNM Submission</h3>
    </div>
    <div class="card-body">
        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">Submitted At</span>
                <span class="font-medium">{{ isset($report['submitted_at']) ? \Carbon\Carbon::parse($report['submitted_at'])->format('d M Y H:i') : 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">Submitted By</span>
                <span class="font-medium">{{ $report['submitted_by_name'] ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">BNM Reference</span>
                <span class="font-mono font-bold text-[--color-accent]">{{ $report['bnm_reference'] }}</span>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">Transaction Details</h3>
    </div>
    <div class="card-body">
        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">Transaction ID</span>
                <span class="font-mono">#{{ $report['transaction_id'] ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">Branch</span>
                <span class="font-medium">{{ $report['branch_name'] ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">Created At</span>
                <span class="font-medium">{{ isset($report['created_at']) ? \Carbon\Carbon::parse($report['created_at'])->format('d M Y H:i') : 'N/A' }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
