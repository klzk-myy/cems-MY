@extends('layouts.base')

@section('title', 'Customer KYC')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Customer KYC Information</h3></div>
    <div class="p-6">
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
                                        'Low' => 'bg-green-100 text-green-700',
                                        'Medium' => 'bg-yellow-100 text-yellow-700',
                                        'High' => 'bg-red-100 text-red-700',
                                        'Critical' => 'bg-red-100 text-red-700',
                                        default => 'bg-gray-100 text-gray-700'
                                    };
                                @endphp
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $riskClass }}">{{ $customer->risk_level }}</span>
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
                                        'Simplified' => 'bg-green-100 text-green-700',
                                        'Standard' => 'bg-yellow-100 text-yellow-700',
                                        'Enhanced' => 'bg-red-100 text-red-700',
                                        default => 'bg-gray-100 text-gray-700'
                                    };
                                @endphp
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $cddClass }}">{{ $customer->cdd_level }}</span>
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