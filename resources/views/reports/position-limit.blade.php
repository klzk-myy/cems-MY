@extends('layouts.base')

@section('title', 'Position Limits')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Currency Position Limits</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th class="text-right">Current Position</th>
                    <th class="text-right">Limit</th>
                    <th class="text-right">Utilization</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData ?? [] as $data)
                <tr>
                    <td class="font-mono font-medium">{{ $data['currency'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($data['position'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($data['limit'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format(($data['utilization'] ?? 0) * 100, 1) }}%</td>
                    <td>
                        @if(($data['utilization'] ?? 0) > 0.9)
                            <span class="badge badge-danger">Critical</span>
                        @elseif(($data['utilization'] ?? 0) > 0.75)
                            <span class="badge badge-warning">Warning</span>
                        @else
                            <span class="badge badge-success">OK</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
