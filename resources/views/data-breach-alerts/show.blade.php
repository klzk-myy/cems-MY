@extends('layouts.base')

@section('title', 'Data Breach Alert')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Data Breach Alert #{{ $dataBreachAlert->id ?? 'N/A' }}</h3>
        <a href="{{ route('data-breach-alerts.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                <dd>
                    @if(isset($dataBreachAlert->status))
                        @statuslabel($dataBreachAlert->status)
                    @else
                        <span class="text-[--color-ink-muted]">N/A</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Severity</dt>
                <dd>
                    @if(isset($dataBreachAlert->severity))
                        <span class="badge badge-danger">{{ $dataBreachAlert->severity }}</span>
                    @else
                        <span class="text-[--color-ink-muted]">N/A</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Detected At</dt>
                <dd class="font-mono">{{ $dataBreachAlert->detected_at ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Source</dt>
                <dd class="font-medium">{{ $dataBreachAlert->source ?? 'N/A' }}</dd>
            </div>
        </div>

        <div class="mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-2">Description</h4>
            <p class="text-[--color-ink]">{{ $dataBreachAlert->description ?? 'N/A' }}</p>
        </div>

        @if(!empty($dataBreachAlert->affected_data))
        <div class="mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-2">Affected Data</h4>
            <div class="space-y-2">
                @foreach($dataBreachAlert->affected_data as $data)
                <div class="p-3 bg-[--color-surface-elevated] rounded font-mono text-sm">
                    {{ $data }}
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex gap-3">
            <form method="POST" action="{{ route('data-breach-alerts.acknowledge', $dataBreachAlert->id ?? 0) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Acknowledge</button>
            </form>
            <form method="POST" action="{{ route('data-breach-alerts.resolve', $dataBreachAlert->id ?? 0) }}">
                @csrf
                <button type="submit" class="btn btn-success">Mark Resolved</button>
            </form>
        </div>
    </div>
</div>
@endsection