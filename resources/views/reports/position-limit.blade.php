@extends('layouts.base')

@section('title', 'Position Limits')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Currency Position Limits</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
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
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Critical</span>
                        @elseif(($data['utilization'] ?? 0) > 0.75)
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Warning</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">OK</span>
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