@extends('layouts.base')

@section('title', 'Open Counter')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Open Counter - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="card-body">
        @if(session('error'))
        <div class="alert alert-danger mb-6">
            <span>{{ session('error') }}</span>
        </div>
        @endif

        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Opening Float (MYR)</label>
                    <input type="number" step="0.01" wire:model="openingFloat" class="form-input" required>
                    @error('openingFloat')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label class="form-label">Notes</label>
                    <textarea wire:model="notes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Open Counter</span>
                    <span wire:loading>Opening...</span>
                </button>
                <a href="{{ route('counters.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
