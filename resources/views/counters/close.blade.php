@extends('layouts.base')

@section('title', 'Close Counter')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Close Counter - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="card-body">
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Session Summary</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Opened At</dt>
                    <dd class="font-mono">{{ $session->opened_at ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Opened By</dt>
                    <dd class="font-medium">{{ $session->user_name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Opening Float</dt>
                    <dd class="font-mono">RM {{ number_format($session->opening_float ?? 0, 2) }}</dd>
                </div>
            </dl>
        </div>

        <form method="POST" action="{{ route('counters.close', $counter->id ?? 0) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Closing Float (MYR)</label>
                    <input type="number" step="0.01" name="closing_float" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Cash In Hand</label>
                    <input type="number" step="0.01" name="cash_in_hand" class="form-input" required>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Close Counter</button>
                <a href="{{ route('counters.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection