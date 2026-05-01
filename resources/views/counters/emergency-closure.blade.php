@extends('layouts.base')

@section('title', 'Emergency Closure Details')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Emergency Closure Details - {{ $counter->code }}</h3>
    </div>
    <div class="p-6">
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
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Acknowledged</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending Acknowledgment</span>
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
            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">Acknowledge Closure</button>
        </form>
        @endif

        <a href="{{ route('counters.index') }}" class="btn btn-secondary mt-4 inline-block">Back to Counters</a>
    </div>
</div>
@endsection