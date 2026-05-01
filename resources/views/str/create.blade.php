@extends('layouts.base')

@section('title', 'Create STR - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Create STR</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Suspicious Transaction Report</p>
    </div>
    <a href="{{ route('str.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        Back to STR List
    </a>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Select Alert to Report</h3>
    </div>
    <div class="p-6">
        @if($pendingAlerts->count() > 0)
        <form action="{{ route('str.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Select Alert</label>
                    <select name="alert_id" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm">
                        <option value="">-- Select an alert --</option>
                        @foreach($pendingAlerts as $alert)
                        <option value="{{ $alert->id }}">
                            Alert #{{ $alert->id }} - {{ $alert->transaction->customer->full_name ?? 'Unknown' }}
                            ({{ $alert->transaction->amount_local ?? 0 }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">STR Reason (min 20 chars)</label>
                    <textarea name="reason" rows="4" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm"
                        placeholder="Describe why this transaction is suspicious..."></textarea>
                </div>
                <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                    Create STR Draft
                </button>
            </div>
        </form>
        @else
        <p class="text-sm text-[--color-ink-muted]">No pending alerts available for STR creation.</p>
        @endif
    </div>
</div>
@endsection