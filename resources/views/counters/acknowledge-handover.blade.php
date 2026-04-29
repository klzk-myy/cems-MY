@extends('layouts.base')

@section('title', 'Acknowledge Handover')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Acknowledge Handover - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="card-body">
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Handover Details</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">From</dt>
                    <dd class="font-medium">{{ $handover->fromUser->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Supervised By</dt>
                    <dd class="font-medium">{{ $handover->supervisor->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Handover Time</dt>
                    <dd class="font-mono">{{ $handover->handover_time?->toIso8601String() ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Variance (MYR)</dt>
                    <dd class="font-mono">{{ $handover->variance_myr ?? '0.00' }}</dd>
                </div>
            </dl>
        </div>

        <form method="POST" action="{{ route('counters.handover.acknowledge', $counter->code ?? 0) }}">
            @csrf
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-4">
                    <input type="checkbox" name="verified" id="verified" value="1" class="w-4 h-4" required>
                    <label for="verified" class="text-sm font-medium">I confirm the physical count has been verified and matches the expected balance</label>
                </div>
            </div>
            <div class="mb-6">
                <label class="form-label">Notes (optional)</label>
                <textarea name="notes" class="form-input" rows="2" placeholder="Any notes about the handover..."></textarea>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Acknowledge Handover</button>
                <a href="{{ route('counters.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection