@extends('layouts.base')

@section('title', 'Finding Detail')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Finding Detail</h1>
    <p class="text-sm text-[--color-ink-muted]">
        @switch($finding['finding_type'] ?? 'Unknown')
            @case('VelocityExceeded') Velocity Exceeded @break
            @case('StructuringPattern') Structuring Pattern @break
            @case('SanctionMatch') Sanction Match @break
            @case('StrDeadline') STR Deadline @break
            @case('CounterfeitAlert') Counterfeit Alert @break
            @default {{ $finding['finding_type'] ?? 'N/A' }}
        @endswitch
    </p>
</div>
@endsection

@section('header-actions')
<a href="/compliance/findings" class="btn btn-ghost">Back to Findings</a>
@endsection

@section('content')
<div class="grid grid-cols-2 gap-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Finding Information</h3>
            @switch($finding['severity'] ?? 'Low')
                @case('Critical')
                    <span class="badge bg-red-900 text-white">Critical</span>
                    @break
                @case('High')
                    <span class="badge badge-danger">High</span>
                    @break
                @case('Medium')
                    <span class="badge badge-warning">Medium</span>
                    @break
                @case('Low')
                    <span class="badge badge-success">Low</span>
                    @break
                @default
                    <span class="badge badge-default">{{ $finding['severity'] ?? 'N/A' }}</span>
            @endswitch
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Type</span>
                    <span class="font-medium">{{ $finding['finding_type'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Severity</span>
                    <span class="font-medium">{{ $finding['severity'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Status</span>
                    @switch($finding['status'] ?? 'New')
                        @case('New')
                            <span class="badge badge-info">New</span>
                            @break
                        @case('Reviewed')
                            <span class="badge badge-warning">Reviewed</span>
                            @break
                        @case('Dismissed')
                            <span class="badge badge-default">Dismissed</span>
                            @break
                        @case('CaseCreated')
                            <span class="badge badge-success">Case Created</span>
                            @break
                        @default
                            <span class="badge badge-default">{{ $finding['status'] ?? 'N/A' }}</span>
                    @endswitch
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Generated</span>
                    <span class="font-medium">{{ isset($finding['generated_at']) ? \Carbon\Carbon::parse($finding['generated_at'])->format('d M Y H:i') : 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Subject Information</h3>
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Subject Type</span>
                    <span class="font-medium">{{ $finding['subject_type'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Subject ID</span>
                    @if(($finding['subject_type'] ?? '') === 'Customer')
                        <a href="/customers/{{ $finding['subject_id'] }}" class="text-[--color-accent] hover:underline font-mono">
                            #{{ $finding['subject_id'] ?? 'N/A' }}
                        </a>
                    @elseif(($finding['subject_type'] ?? '') === 'Transaction')
                        <a href="/transactions/{{ $finding['subject_id'] }}" class="text-[--color-accent] hover:underline font-mono">
                            #{{ $finding['subject_id'] ?? 'N/A' }}
                        </a>
                    @else
                        <span class="font-mono">#{{ $finding['subject_id'] ?? 'N/A' }}</span>
                    @endif
                </div>
                @if(isset($finding['customer_name']))
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Customer Name</span>
                    <span class="font-medium">{{ $finding['customer_name'] }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if(!empty($finding['details']))
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">Details</h3>
    </div>
    <div class="card-body">
        <pre class="text-sm whitespace-pre-wrap">{{ json_encode($finding['details'], JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
@endif

@if(($finding['status'] ?? 'New') === 'New')
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">Actions</h3>
    </div>
    <div class="card-body">
        <div class="flex gap-4">
            <form method="POST" action="/compliance/findings/{{ $finding['id'] }}/dismiss" class="flex-1">
                @csrf
                <div class="form-group mb-3">
                    <label class="form-label">Dismiss Finding (provide reason)</label>
                    <textarea name="reason" class="form-input" rows="2" required placeholder="Explain why this is a false positive..."></textarea>
                </div>
                <button type="submit" class="btn btn-secondary w-full">Dismiss Finding</button>
            </form>
            <div class="flex-1">
                <p class="text-sm text-[--color-ink-muted] mb-3">Or create a compliance case to investigate further.</p>
                <a href="/compliance/cases/create?finding_id={{ $finding['id'] }}" class="btn btn-primary w-full">Create Case</a>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
