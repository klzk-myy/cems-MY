@extends('layouts.base')

@section('title', 'Counter Handover')

@section('content')
<div class="card max-w-2xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Counter Handover - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="p-6">
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Current Session</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Current Holder</dt>
                    <dd class="font-medium">{{ $session->user_name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Opened At</dt>
                    <dd class="font-mono">{{ $session->opened_at ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>

        <form method="POST" action="{{ route('counters.handover', $counter->id ?? 0) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Transfer To</label>
                    <select name="transfer_to" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="">Select User</option>
                        @foreach($availableUsers ?? [] as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Supervisor Approval</label>
                    <select name="supervisor_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="">Select Supervisor</option>
                        @foreach($supervisors ?? [] as $supervisor)
                        <option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Notes</label>
                    <textarea name="notes" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">Confirm Handover</button>
                <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection