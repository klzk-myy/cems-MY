@extends('layouts.base')

@section('title', 'Emergency Counter Closure')

@section('content')
<div class="card max-w-2xl">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-red-600">Emergency Counter Closure - {{ $counter->code }}</h3>
    </div>
    <div class="p-6">
        <div class="bg-red-50 border border-red-200 p-4 rounded-lg mb-6">
            <p class="text-red-800 text-sm">
                <strong>Warning:</strong> This will immediately close the counter without variance calculation.
                The session will be marked as Emergency Closed and a manager will be notified.
            </p>
        </div>

        <dl class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Session Information</h4>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Counter</dt>
                    <dd class="font-medium">{{ $counter->code }} - {{ $counter->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Current Teller</dt>
                    <dd class="font-medium">{{ $session->user->username ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Session Opened</dt>
                    <dd class="font-mono">{{ $session->opened_at->toDateTimeString() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Session Duration</dt>
                    <dd class="font-mono">{{ $session->opened_at->diffForHumans() }}</dd>
                </div>
            </div>
        </dl>

        <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg mb-6">
            <ul class="text-yellow-800 text-sm list-disc list-inside">
                <li>You cannot perform emergency close more than once every 4 hours</li>
                <li>Session must be at least 30 minutes old</li>
                <li>Manager will be notified immediately</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('counters.emergency', $counter->code) }}">
            @csrf
            <div class="mb-6">
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Reason for Emergency Closure</label>
                <textarea name="reason" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="3" required placeholder="Explain why this emergency closure is necessary..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700">
                    Confirm Emergency Closure
                </button>
                <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection