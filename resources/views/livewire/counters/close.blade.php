@extends('layouts.base')

@section('title', 'Close Counter')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Close Counter - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="card-body">
        @if(session('error'))
        <div class="alert alert-danger mb-6">
            <span>{{ session('error') }}</span>
        </div>
        @endif

        @if($session)
        <div class="bg-gray-50 p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-gray-500 mb-4">Session Summary</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-gray-500">Opened At</dt>
                    <dd class="font-mono">{{ $session->opened_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Opened By</dt>
                    <dd class="font-medium">{{ $session->user->username ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">Opening Float</dt>
                    <dd class="font-mono">RM {{ number_format($session->opening_float ?? 0, 2) }}</dd>
                </div>
            </dl>
        </div>
        @endif

        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Closing Float (MYR)</label>
                    <input type="number" step="0.01" wire:model="closingFloat" class="form-input" required>
                    @error('closingFloat')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label class="form-label">Cash In Hand</label>
                    <input type="number" step="0.01" wire:model="cashInHand" class="form-input" required>
                    @error('cashInHand')
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
                    <span wire:loading.remove>Close Counter</span>
                    <span wire:loading>Closing...</span>
                </button>
                <a href="{{ route('counters.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
