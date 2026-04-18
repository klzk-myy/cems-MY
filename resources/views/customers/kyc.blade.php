@extends('layouts.base')

@section('title', 'Customer KYC')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Customer KYC Information</h3></div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Basic Information</h4>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Name</dt>
                        <dd class="font-medium">{{ $customer->full_name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">ID Type</dt>
                        <dd class="font-mono">{{ $customer->id_type ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Risk Level</dt>
                        <dd>
                            @if(isset($customer->risk_level))
                                @php
                                    $riskClass = match($customer->risk_level ?? '') {
                                        'Low' => 'badge-success',
                                        'Medium' => 'badge-warning',
                                        'High' => 'badge-danger',
                                        'Critical' => 'badge-danger',
                                        default => 'badge-default'
                                    };
                                @endphp
                                <span class="badge {{ $riskClass }}">{{ $customer->risk_level }}</span>
                            @else
                                <span class="text-[--color-ink-muted]">N/A</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">CDD Level</dt>
                        <dd>
                            @if(isset($customer->cdd_level))
                                @php
                                    $cddClass = match($customer->cdd_level ?? '') {
                                        'Simplified' => 'badge-info',
                                        'Standard' => 'badge-warning',
                                        'Enhanced' => 'badge-danger',
                                        default => 'badge-default'
                                    };
                                @endphp
                                <span class="badge {{ $cddClass }}">{{ $customer->cdd_level }}</span>
                            @else
                                <span class="text-[--color-ink-muted]">N/A</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
            <div>
                <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Documents</h4>
                <div class="space-y-3">
                    @forelse($documents ?? [] as $doc)
                    <div class="flex items-center gap-3 p-3 bg-[--color-surface-elevated] rounded">
                        <span class="font-mono text-sm">{{ $doc['type'] ?? 'N/A' }}</span>
                        <span class="text-[--color-ink-muted]">{{ $doc['uploaded_at'] ?? '' }}</span>
                    </div>
                    @empty
                    <p class="text-[--color-ink-muted] text-sm">No documents uploaded</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
