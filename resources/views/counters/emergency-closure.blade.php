@extends('layouts.base')

@section('title', 'Emergency Closure Details')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Emergency Closure Details - {{ $counter->code }}</h3>
    </div>
    <div class="card-body">
        <dl class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Closed At</dt>
                    <dd class="font-mono">{{ $closure->closed_at->toDateTimeString() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Teller</dt>
                    <dd class="font-medium">{{ $closure->teller->username }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Reason</dt>
                    <dd class="font-medium">{{ $closure->reason }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                    <dd class="font-medium">
                        @if($closure->acknowledged_at)
                            <span class="badge badge-success">Acknowledged</span>
                        @else
                            <span class="badge badge-warning">Pending Acknowledgment</span>
                        @endif
                    </dd>
                </div>
            </div>
        </dl>

        @if(!empty($variance))
        <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Variance Summary</h4>
        <table class="w-full mb-6">
            <thead>
                <tr>
                    <th class="text-left text-sm text-[--color-ink-muted]">Currency</th>
                    <th class="text-right text-sm text-[--color-ink-muted]">Expected</th>
                    <th class="text-right text-sm text-[--color-ink-muted]">Actual</th>
                    <th class="text-right text-sm text-[--color-ink-muted]">Variance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($variance as $currency => $data)
                <tr class="border-t border-[--color-border-subtle]">
                    <td class="py-2 font-mono">{{ $currency }}</td>
                    <td class="py-2 text-right font-mono">{{ number_format($data['expected'], 2) }}</td>
                    <td class="py-2 text-right font-mono">{{ number_format($data['actual'], 2) }}</td>
                    <td class="py-2 text-right font-mono {{ bccomp($data['variance'], '0', 4) != 0 ? 'text-red-600' : '' }}">
                        {{ number_format($data['variance'], 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(!$closure->acknowledged_at)
        <form method="POST" action="{{ route('counters.emergency.acknowledge', [$counter->code, $closure->id]) }}">
            @csrf
            <button type="submit" class="btn btn-primary">Acknowledge Closure</button>
        </form>
        @endif

        <a href="{{ route('counters.index') }}" class="btn btn-secondary mt-4">Back to Counters</a>
    </div>
</div>
@endsection