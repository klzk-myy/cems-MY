@extends('layouts.base')

@section('title', 'Currency Revaluation')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Currency Revaluation</h3>
        <a href="/accounting/revaluation/run" class="btn btn-primary btn-sm">Run Revaluation</a>
    </div>
    <div class="table-container">
        <table class="table">
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
                            <span class="badge badge-warning">Pending</span>
                        @else
                            <span class="badge badge-success">Current</span>
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
