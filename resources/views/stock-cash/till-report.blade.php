@extends('layouts.base')

@section('title', 'Till Report')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Till Report - {{ $date }}</h3>
        <a href="{{ route('stock-cash.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Till ID</th>
                    <th>Currency</th>
                    <th>Opened By</th>
                    <th class="text-right">Opening</th>
                    <th>Closed By</th>
                    <th class="text-right">Closing</th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances ?? [] as $balance)
                <tr>
                    <td class="font-mono">{{ $balance->till_id ?? 'N/A' }}</td>
                    <td class="font-mono">{{ $balance->currency->code ?? 'N/A' }}</td>
                    <td>{{ $balance->opener->name ?? 'N/A' }}</td>
                    <td class="font-mono text-right">RM {{ number_format($balance->opening_balance ?? 0, 2) }}</td>
                    <td>{{ $balance->closer->name ?? '-' }}</td>
                    <td class="font-mono text-right">
                        @if($balance->closing_balance)
                            RM {{ number_format($balance->closing_balance, 2) }}
                        @else
                            <span class="text-[--color-ink-muted]">Open</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No data found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection