@extends('layouts.base')

@section('title', 'Counter Handover')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Counter Handover - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="card-body">
        @if(session('error'))
        <div class="alert alert-danger mb-6">
            <span>{{ session('error') }}</span>
        </div>
        @endif

        @if($session)
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Current Session</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Current Holder</dt>
                    <dd class="font-medium">{{ $session->user->username ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Opened At</dt>
                    <dd class="font-mono">{{ $session->opened_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>
        @endif

        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Transfer To</label>
                    <select wire:model="transferToUserId" class="form-select" required>
                        <option value="">Select User</option>
                        @foreach($availableUsers as $user)
                        <option value="{{ $user['id'] }}">{{ $user['name'] }} ({{ $user['username'] }})</option>
                        @endforeach
                    </select>
                    @error('transferToUserId')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label class="form-label">Supervisor Approval</label>
                    <select wire:model="supervisorId" class="form-select" required>
                        <option value="">Select Supervisor</option>
                        @foreach($supervisors as $supervisor)
                        <option value="{{ $supervisor['id'] }}">{{ $supervisor['name'] }} ({{ $supervisor['username'] }})</option>
                        @endforeach
                    </select>
                    @error('supervisorId')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea wire:model="notes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Confirm Handover</span>
                    <span wire:loading>Processing...</span>
                </button>
                <a href="{{ route('counters.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
