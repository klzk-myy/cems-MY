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
<a href="{{ route('compliance.findings.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Back to Findings</a>
@endsection

@section('content')
<div class="grid grid-cols-2 gap-6">
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Finding Information</h3>
            @switch($finding['severity'] ?? 'Low')
                @case('Critical')
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-900 text-white">Critical</span>
                    @break
                @case('High')
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">High</span>
                    @break
                @case('Medium')
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Medium</span>
                    @break
                @case('Low')
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Low</span>
                    @break
                @default
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">{{ $finding['severity'] ?? 'N/A' }}</span>
            @endswitch
        </div>
        <div class="p-6">
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
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">New</span>
                            @break
                        @case('Reviewed')
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Reviewed</span>
                            @break
                        @case('Dismissed')
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Dismissed</span>
                            @break
                        @case('CaseCreated')
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Case Created</span>
                            @break
                        @default
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">{{ $finding['status'] ?? 'N/A' }}</span>
                    @endswitch
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Generated</span>
                    <span class="font-medium">{{ isset($finding['generated_at']) ? \Carbon\Carbon::parse($finding['generated_at'])->format('d M Y H:i') : 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Subject Information</h3>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Subject Type</span>
                    <span class="font-medium">{{ $finding['subject_type'] ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[--color-ink-muted]">Subject ID</span>
                    @if(($finding['subject_type'] ?? '') === 'Customer')
                        <a href="{{ route('customers.show', $finding['subject_id']) }}" class="text-[--color-accent] hover:underline font-mono">
                            #{{ $finding['subject_id'] ?? 'N/A' }}
                        </a>
                    @elseif(($finding['subject_type'] ?? '') === 'Transaction')
                        <a href="{{ route('transactions.show', $finding['subject_id']) }}" class="text-[--color-accent] hover:underline font-mono">
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
<div class="bg-white border border-[--color-border] rounded-xl mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Details</h3>
    </div>
    <div class="p-6">
        <pre class="text-sm whitespace-pre-wrap">{{ json_encode($finding['details'], JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
@endif

@if(($finding['status'] ?? 'New') === 'New')
<div class="bg-white border border-[--color-border] rounded-xl mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Actions</h3>
    </div>
    <div class="p-6">
        <div class="flex gap-4">
            <form method="POST" action="{{ route('compliance.findings.dismiss', $finding['id']) }}" class="flex-1">
                @csrf
                <div class="mb-3">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Dismiss Finding (provide reason)</label>
                    <textarea name="reason" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2" required placeholder="Explain why this is a false positive..."></textarea>
                </div>
                <button type="submit" class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Dismiss Finding</button>
            </form>
            <div class="flex-1">
                <p class="text-sm text-[--color-ink-muted] mb-3">Or create a compliance case to investigate further.</p>
                <a href="{{ route('compliance.cases.create', ['finding_id' => $finding['id']]) }}" class="block w-full px-4 py-2 text-sm font-medium text-center rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">Create Case</a>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
