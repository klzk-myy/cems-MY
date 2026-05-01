@extends('layouts.base')

@section('title', 'Currency Revaluation')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Currency Revaluation</h3>
        <a href="/accounting/revaluation/run" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-primary]/90">Run Revaluation</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th>Current Rate</th>
                    <th>Previous Rate</th>
                    <th>Unrealized P/L</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions ?? [] as $position)
                <tr>
                    <td class="font-mono font-medium">{{ $position->currency_code }}</td>
                    <td class="font-mono">{{ number_format($position->current_rate, 4) }}</td>
                    <td class="font-mono">{{ number_format($position->previous_rate, 4) }}</td>
                    <td class="font-mono {{ $position->unrealized_pl >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                        {{ number_format($position->unrealized_pl, 2) }} MYR
                    </td>
                    <td>
                        @if($position->needs_revaluation)
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Current</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No positions</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection