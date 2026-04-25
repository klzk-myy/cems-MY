@extends('layouts.base')

@section('title', 'Stock & Cash')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Stock & Cash</h1>
    <p class="text-sm text-[--color-ink-muted]">Currency position and cash management</p>
</div>
@endsection

@section('content')
{{-- Position Summary --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
    {{-- MYR Cash Card --}}
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-success]/10 text-[--color-success]">
                <span class="font-bold">MYR</span>
            </div>
        </div>
        <p class="stat-card-label">Cash in Hand</p>
        <p class="stat-card-value">{{ number_format($myrCashInHand ?? 0, 2) }}</p>
        <p class="stat-card-change text-[--color-ink-muted]">Ringgit Malaysia</p>
    </div>

    {{-- Foreign Currency Cards --}}
    @foreach($positions ?? [] as $position)
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-info]/10 text-[--color-info]">
                <span class="font-bold">{{ $position->currency_code }}</span>
            </div>
        </div>
        <p class="stat-card-label">{{ $position->currency_name ?? $position->currency_code }}</p>
        <p class="stat-card-value">{{ number_format($position->quantity, 2) }}</p>
        <p class="stat-card-change text-[--color-ink-muted]">
            Avg Cost: {{ number_format($position->avg_cost, 4) }}
        </p>
    </div>
    @endforeach
</div>

{{-- Currency Positions Table --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Currency Positions</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th>Quantity</th>
                    <th>Avg Cost</th>
                    <th>Market Value (MYR)</th>
                    <th>Unrealized P/L</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions ?? [] as $position)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center font-bold text-xs">
                                {{ substr($position->currency_code, 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium">{{ $position->currency_code }}</p>
                                <p class="text-xs text-[--color-ink-muted]">{{ $position->currency_name ?? '' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="font-mono">{{ number_format($position->quantity, 2) }}</td>
                    <td class="font-mono">{{ number_format($position->avg_cost, 4) }}</td>
                    <td class="font-mono">{{ number_format($position->market_value, 2) }} MYR</td>
                    <td>
                        @php
                            $pl = $position->unrealized_pl ?? 0;
                            $plClass = $pl >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]';
                        @endphp
                        <span class="font-mono {{ $plClass }}">
                            {{ $pl >= 0 ? '+' : '' }}{{ number_format($pl, 2) }} MYR
                        </span>
                    </td>
                    <td>
                        <a href="/stock-cash/position/{{ $position->id }}" class="btn btn-ghost btn-sm">Details</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No positions found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
