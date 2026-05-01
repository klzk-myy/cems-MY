@extends('layouts.base')

@section('title', 'Branch Closing - ' . ($branch->code ?? '') . ' - CEMS-MY')

@section('content')
<div class="mb-6">
    <div>
        <div class="flex items-center gap-3">
            <a href="{{ route('branches.show', $branch) }}" class="text-[--color-ink-muted] hover:text-[--color-ink]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-semibold text-[--color-ink]">Branch Closing Workflow</h1>
        </div>
        <p class="text-sm text-[--color-ink-muted] mt-1">{{ $branch->code }} - {{ $branch->name }}</p>
    </div>
</div>

@if(session('success'))
    <div class="mb-6 p-4 bg-green-100 text-green-700 border border-green-200 rounded-lg">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="mb-6 p-4 bg-red-100 text-red-700 border border-red-200 rounded-lg">
        {{ session('error') }}
    </div>
@endif

@if($workflow)
    <div class="card mb-6">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-[--color-ink]">Workflow Status</h3>
                <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full
                    @if($workflow->isInitiated()) bg-yellow-100 text-yellow-700
                    @elseif($workflow->isSettled()) bg-blue-100 text-blue-700
                    @else bg-green-100 text-green-700
                    @endif">
                    {{ ucfirst($workflow->status) }}
                </span>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-[--color-ink-muted]">Initiated by:</span>
                    <span class="ml-2 text-[--color-ink]">{{ $workflow->initiator->username ?? 'N/A' }}</span>
                </div>
                <div>
                    <span class="text-[--color-ink-muted]">Initiated at:</span>
                    <span class="ml-2 text-[--color-ink]">{{ $workflow->created_at->format('Y-m-d H:i') }}</span>
                </div>
                @if($workflow->settlement_at)
                <div>
                    <span class="text-[--color-ink-muted]">Settled at:</span>
                    <span class="ml-2 text-[--color-ink]">{{ $workflow->settlement_at->format('Y-m-d H:i') }}</span>
                </div>
                @endif
                @if($workflow->finalized_at)
                <div>
                    <span class="text-[--color-ink-muted]">Finalized at:</span>
                    <span class="ml-2 text-[--color-ink]">{{ $workflow->finalized_at->format('Y-m-d H:i') }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card mb-6">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Closing Checklist</h3>
        </div>
        <div class="p-6 space-y-3">
            <div class="flex items-center justify-between p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex items-center gap-3">
                    @if($checklist['counters_closed'])
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    @endif
                    <span class="text-sm font-medium text-[--color-ink]">Counters Closed</span>
                </div>
                <span class="text-sm {{ $checklist['counters_closed'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $checklist['counters_closed'] ? 'Complete' : 'Pending' }}
                </span>
            </div>

            <div class="flex items-center justify-between p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex items-center gap-3">
                    @if($checklist['allocations_returned'])
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    @endif
                    <span class="text-sm font-medium text-[--color-ink]">Allocations Returned to Pool</span>
                </div>
                <span class="text-sm {{ $checklist['allocations_returned'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $checklist['allocations_returned'] ? 'Complete' : 'Pending' }}
                </span>
            </div>

            <div class="flex items-center justify-between p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex items-center gap-3">
                    @if($checklist['transfers_complete'])
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    @endif
                    <span class="text-sm font-medium text-[--color-ink]">Transfers Complete</span>
                </div>
                <span class="text-sm {{ $checklist['transfers_complete'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $checklist['transfers_complete'] ? 'Complete' : 'Pending' }}
                </span>
            </div>

            <div class="flex items-center justify-between p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex items-center gap-3">
                    @if($checklist['documents_finalized'])
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    @endif
                    <span class="text-sm font-medium text-[--color-ink]">Documents Finalized</span>
                </div>
                <span class="text-sm {{ $checklist['documents_finalized'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $checklist['documents_finalized'] ? 'Complete' : 'Pending' }}
                </span>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        @if(!$workflow->isFinalized())
            @if($canFinalize)
                <form method="POST" action="{{ route('branch-closing.finalize', $branch) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Finalize Branch Closing
                    </button>
                </form>
            @else
                <span class="px-4 py-2 text-sm text-[--color-ink-muted] bg-[--color-canvas-subtle] rounded-lg">
                    Complete all checklist items to finalize
                </span>
            @endif
        @else
            <span class="px-4 py-2 text-sm text-green-700 bg-green-100 rounded-lg">
                Branch Closing Finalized
            </span>
        @endif
    </div>
@else
    <div class="card">
        <div class="p-6 text-center py-8">
            <p class="text-[--color-ink-muted]">No active closure workflow for this branch.</p>
            <form method="POST" action="{{ route('branch-closing.initiate', $branch) }}" class="mt-4">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Initiate Branch Closing
                </button>
            </form>
        </div>
    </div>
@endif
@endsection