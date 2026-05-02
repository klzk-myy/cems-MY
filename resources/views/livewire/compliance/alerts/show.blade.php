@extends('layouts.base')

@section('title', 'Alert Details')

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('compliance.alerts.index') }}" class="btn btn-ghost btn-icon">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Alert #{{ $alert->id }}</h1>
                <p class="text-sm text-gray-500">{{ $alert->type->label() ?? 'Unknown Type' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if($alert->status->canBeAssigned() && !$alert->assignedTo)
                <div class="flex items-center gap-2">
                    <select wire:model="selectedOfficer" class="form-select w-auto">
                        <option value="">Assign to...</option>
                        @foreach($this->availableOfficers as $officer)
                            <option value="{{ $officer['id'] }}">{{ $officer['username'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="assign($selectedOfficer)" class="btn btn-secondary" {{ !$selectedOfficer ? 'disabled' : '' }}>
                        Assign
                    </button>
                </div>
            @endif
            @if($alert->status->canBeResolved())
                <button type="button" wire:click="resolve()" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Resolve
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Alert Details --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Alert Details</h3>
                    @php
                        $priorityClass = match($alert->priority->value ?? '') {
                            'critical' => 'badge-danger',
                            'high' => 'badge-warning',
                            'medium' => 'badge-info',
                            default => 'badge-default'
                        };
                        $statusClass = match($alert->status->value ?? '') {
                            'Resolved' => 'badge-success',
                            'Rejected' => 'badge-default',
                            'UnderReview' => 'badge-info',
                            default => 'badge-warning'
                        };
                    @endphp
                    <div class="flex gap-2">
                        <span class="badge {{ $priorityClass }}">{{ $alert->priority->label() ?? 'Low' }}</span>
                        <span class="badge {{ $statusClass }}">{{ $alert->status->label() ?? 'Pending' }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Description</p>
                            <p class="text-gray-900">{{ $alert->reason }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Alert Type</p>
                                <p class="font-medium">{{ $alert->type->label() ?? 'Unknown' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Risk Score</p>
                                <p class="font-mono">{{ $alert->risk_score ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Customer Information --}}
            @if($alert->customer)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Related Customer</h3>
                    <a href="{{ route('customers.show', $alert->customer_id) }}" class="btn btn-ghost btn-sm">View Profile</a>
                </div>
                <div class="card-body">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                            <span class="text-lg font-semibold">{{ substr($alert->customer->full_name, 0, 1) }}</span>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-lg">{{ $alert->customer->full_name }}</p>
                            <p class="text-sm text-gray-500">{{ $alert->customer->id_type ?? 'N/A' }}</p>
                            <div class="flex gap-2 mt-2">
                                <span class="badge badge-default">{{ $alert->customer->cdd_level ?? 'N/A' }}</span>
                                @if($alert->customer->pep_status ?? false)
                                    <span class="badge badge-warning">PEP</span>
                                @endif
                                @if($alert->customer->sanction_hit ?? false)
                                    <span class="badge badge-danger">Sanctioned</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Transaction if related --}}
            @if($alert->flaggedTransaction && $alert->flaggedTransaction->transaction)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Related Transaction</h3>
                    <a href="{{ route('transactions.show', $alert->flaggedTransaction->transaction->id) }}" class="btn btn-ghost btn-sm">View</a>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Transaction ID</p>
                            <p class="font-mono">#{{ $alert->flaggedTransaction->transaction->id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Amount</p>
                            <p class="font-mono">{{ number_format($alert->flaggedTransaction->transaction->amount_local, 2) }} MYR</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Type</p>
                            <span class="badge {{ $alert->flaggedTransaction->transaction->type->value === 'Buy' ? 'badge-success' : 'badge-warning' }}">
                                {{ $alert->flaggedTransaction->transaction->type->label() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Status & Assignment --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Assignment</h3>
                </div>
                <div class="card-body space-y-4">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Assigned To</p>
                        @if($alert->assignedTo)
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-xs">
                                    {{ substr($alert->assignedTo->username, 0, 1) }}
                                </div>
                                <span class="font-medium">{{ $alert->assignedTo->username }}</span>
                            </div>
                        @else
                            <span class="badge badge-warning">Unassigned</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Created At</p>
                        <p class="text-sm">{{ $alert->created_at->format('d M Y, H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Last Updated</p>
                        <p class="text-sm">{{ $alert->updated_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Actions</h3>
                </div>
                <div class="card-body space-y-2">
                    @if($alert->status->canBeResolved())
                        <button type="button" wire:click="resolve()" class="btn btn-primary w-full">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Mark as Resolved
                        </button>
                    @endif
                    @if(!$alert->status->isResolved())
                        <button type="button" wire:click="dismiss()" class="btn btn-secondary w-full">
                            Dismiss Alert
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
