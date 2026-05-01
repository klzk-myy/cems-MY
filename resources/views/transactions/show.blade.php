@extends('layouts.base')

@section('title', 'Transaction #' . ($transaction->id ?? ''))

@section('header-title')
<div class="flex items-center gap-2">
    <a href="/transactions" class="p-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-semibold text-[--color-ink]">Transaction #{{ $transaction->id ?? '' }}</h1>
        <p class="text-sm text-[--color-ink-muted]">{{ $transaction->created_at->format('d M Y, H:i:s') }}</p>
    </div>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    @if(($transaction->status->value ?? '') === 'PendingApproval')
        @if(auth()->check() && auth()->user()->isManager() && auth()->id() !== $transaction->user_id)
        <form method="POST" action="/transactions/{{ $transaction->id }}/approve" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Approve
            </button>
        </form>
        @endif
    @endif
    @if(($transaction->status->value ?? '') === 'Completed')
        <a href="/transactions/{{ $transaction->id }}/receipt" class="inline-flex items-center gap-2 px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]" target="_blank">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print Receipt
        </a>
    @endif
    @if(($transaction->status->value ?? '') !== 'Cancelled')
        @can('cancel', $transaction)
        <a href="/transactions/{{ $transaction->id }}/cancel" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            Cancel
        </a>
        @endcan
    @endif
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Main Details --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Transaction Details --}}
        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border] flex items-center justify-between">
                <h3 class="text-base font-semibold text-[--color-ink]">Transaction Details</h3>
                @php
                    $statusClass = match($transaction->status->value ?? '') {
                        'Completed' => 'badge-success',
                        'PendingApproval' => 'badge-warning',
                        'PendingCancellation' => 'badge-warning',
                        'Cancelled' => 'badge-danger',
                        default => 'badge-default'
                    };
                @endphp
                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $statusClass === 'badge-success' ? 'bg-green-100 text-green-700' : ($statusClass === 'badge-warning' ? 'bg-yellow-100 text-yellow-700' : ($statusClass === 'badge-danger' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">{{ $transaction->status->label() ?? '' }}</span>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Transaction Type</p>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $transaction->type->value === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $transaction->type->label() ?? '' }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Currency</p>
                        <p class="font-mono font-medium">{{ $transaction->currency_code ?? '' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Foreign Amount</p>
                        <p class="font-mono text-xl font-semibold">{{ number_format($transaction->amount_foreign ?? 0, 2) }} {{ $transaction->currency_code ?? '' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Exchange Rate</p>
                        <p class="font-mono text-xl font-semibold">{{ $transaction->rate ?? '' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm text-[--color-ink-muted] mb-1">MYR Value</p>
                        <p class="font-mono text-3xl font-bold text-[--color-accent]">{{ number_format($transaction->amount_local ?? 0, 2) }} MYR</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Customer Information --}}
        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border] flex items-center justify-between">
                <h3 class="text-base font-semibold text-[--color-ink]">Customer Information</h3>
                <a href="/customers/{{ $transaction->customer_id }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">View Profile</a>
            </div>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-[--color-canvas-subtle] rounded-xl flex items-center justify-center">
                        <span class="text-lg font-semibold">{{ substr($transaction->customer->full_name ?? 'N/A', 0, 1) }}</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-lg">{{ $transaction->customer->full_name ?? 'N/A' }}</p>
                        <p class="text-sm text-[--color-ink-muted]">{{ $transaction->customer->ic_number ?? 'N/A' }}</p>
                        <div class="flex gap-4 mt-2">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">{{ $transaction->cdd_level?->label() ?? 'N/A' }}</span>
                            @if($transaction->customer->is_pep ?? false)
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">PEP</span>
                            @endif
                            @if($transaction->customer->is_sanctioned ?? false)
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Sanctioned</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Audit Trail --}}
        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Audit Trail</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($transaction->auditLogs ?? [] as $log)
                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 rounded-full bg-[--color-border-strong] mt-2"></div>
                        <div class="flex-1">
                            <p class="text-sm">{{ $log->description ?? 'Action taken' }}</p>
                            <p class="text-xs text-[--color-ink-muted]">
                                {{ $log->user->username ?? 'System' }} - {{ $log->created_at->format('d M Y, H:i:s') }}
                            </p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-[--color-ink-muted]">No audit logs available</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Counter & Branch --}}
        <div class="bg-white border border-[--color-border] rounded-xl">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Location</h3>
            </div>
            <div class="p-6 space-y-3">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Counter</p>
                    <p class="font-medium">{{ $transaction->counter->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Branch</p>
                    <p class="font-medium">{{ $transaction->branch->name ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        {{-- Compliance Flags --}}
        @if($transaction->flags && $transaction->flags->count() > 0)
        <div class="bg-white border border-[--color-border] rounded-xl">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Compliance Flags</h3>
            </div>
            <div class="p-6 space-y-2">
                @foreach($transaction->flags as $flag)
                <div class="flex items-center gap-2 p-2 bg-[--color-danger]/5 rounded-lg border border-[--color-danger]/10">
                    <svg class="w-4 h-4 text-[--color-danger]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span class="text-sm font-medium text-[--color-danger]">{{ $flag->type->label() }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Created By --}}
        <div class="bg-white border border-[--color-border] rounded-xl">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Created By</h3>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center">
                        <span class="font-semibold">{{ substr($transaction->creator->username ?? 'N/A', 0, 1) }}</span>
                    </div>
                    <div>
                        <p class="font-medium">{{ $transaction->creator->username ?? 'N/A' }}</p>
                        <p class="text-xs text-[--color-ink-muted]">{{ $transaction->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
