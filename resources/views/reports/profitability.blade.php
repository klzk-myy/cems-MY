@extends('layouts.base')

@section('title', 'Profitability Analysis')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Profitability Analysis</h3>
        <span class="text-sm text-[--color-ink-muted]">{{ $startDate ?? '' }} - {{ $endDate ?? '' }}</span>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Currency</th>
                        <th class="text-right">Volume</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">P/L</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($positions ?? [] as $position)
                    <tr>
                        <td class="font-mono">{{ $position['currency'] ?? 'N/A' }}</td>
                        <td class="font-mono text-right">{{ number_format($position['volume'] ?? 0, 2) }}</td>
                        <td class="font-mono text-right">{{ number_format($position['revenue'] ?? 0, 2) }}</td>
                        <td class="font-mono text-right {{ ($position['profit'] ?? 0) >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                            {{ number_format($position['profit'] ?? 0, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
