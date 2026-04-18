@extends('layouts.base')

@section('title', 'Screening - ' . ($customer->name ?? 'Customer'))

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Sanction Screening</h1>
    <p class="text-sm text-[--color-ink-muted]">{{ $customer->name ?? 'N/A' }}</p>
</div>
@endsection

@section('header-actions')
<form method="POST" action="/compliance/screening/{{ $customer->id }}" class="inline">
    @csrf
    <button type="submit" class="btn btn-primary">Re-screen Customer</button>
</form>
<a href="/customers/{{ $customer->id }}" class="btn btn-ghost">Back to Customer</a>
@endsection

@section('content')
<div class="grid grid-cols-3 gap-6 mb-6">
    <div class="card">
        <div class="card-body text-center">
            <p class="text-sm text-[--color-ink-muted] mb-2">Status</p>
            @if(($status['sanction_hit'] ?? false))
                <span class="badge badge-danger text-lg px-4 py-2">Sanction Hit</span>
            @else
                <span class="badge badge-success text-lg px-4 py-2">Clear</span>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <p class="text-sm text-[--color-ink-muted] mb-2">Last Result</p>
            <p class="text-2xl font-bold">
                @switch($status['last_result'] ?? 'clear')
                    @case('clear')
                        <span class="text-green-600">Clear</span>
                        @break
                    @case('flag')
                        <span class="text-yellow-600">Flag</span>
                        @break
                    @case('block')
                        <span class="text-red-600">Block</span>
                        @break
                    @default
                        <span class="text-[--color-ink-muted]">N/A</span>
                @endswitch
            </p>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <p class="text-sm text-[--color-ink-muted] mb-2">Last Match Score</p>
            <p class="text-2xl font-bold font-mono">
                {{ isset($status['last_match_score']) ? number_format($status['last_match_score'], 1) . '%' : 'N/A' }}
            </p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Screening History</h3>
        <p class="text-sm text-[--color-ink-muted]">Last screened: {{ isset($status['last_screened_at']) ? \Carbon\Carbon::parse($status['last_screened_at'])->format('d M Y H:i') : 'Never' }}</p>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Score</th>
                    <th>Result</th>
                    <th>Matches</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $record)
                <tr>
                    <td>{{ isset($record['screened_at']) ? \Carbon\Carbon::parse($record['screened_at'])->format('d M Y H:i') : 'N/A' }}</td>
                    <td class="font-mono">
                        @if(isset($record['confidence_score']))
                            @if($record['confidence_score'] >= 90)
                                <span class="text-red-600 font-bold">{{ number_format($record['confidence_score'], 1) }}%</span>
                            @elseif($record['confidence_score'] >= 75)
                                <span class="text-yellow-600 font-bold">{{ number_format($record['confidence_score'], 1) }}%</span>
                            @else
                                <span class="text-green-600">{{ number_format($record['confidence_score'], 1) }}%</span>
                            @endif
                        @else
                            N/A
                        @endif
                    </td>
                    <td>
                        @switch($record['action'] ?? 'clear')
                            @case('clear')
                                <span class="badge badge-success">Clear</span>
                                @break
                            @case('flag')
                                <span class="badge badge-warning">Flag</span>
                                @break
                            @case('block')
                                <span class="badge badge-danger">Block</span>
                                @break
                            @default
                                <span class="badge badge-default">{{ $record['action'] ?? 'N/A' }}</span>
                        @endswitch
                    </td>
                    <td>{{ count($record['matches'] ?? []) }}</td>
                    <td>
                        @if(!empty($record['matches']))
                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleMatches(this)">View Matches</button>
                        @else
                            <span class="text-[--color-ink-muted]">-</span>
                        @endif
                    </td>
                </tr>
                @if(!empty($record['matches']))
                <tr class="hidden match-details-row">
                    <td colspan="5" class="bg-gray-50 p-4">
                        <div class="space-y-2">
                            @foreach($record['matches'] as $match)
                            <div class="bg-white p-3 rounded border">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium">{{ $match['entity_name'] ?? 'N/A' }}</p>
                                        <p class="text-sm text-[--color-ink-muted]">{{ $match['list_name'] ?? 'N/A' }} - {{ $match['list_source'] ?? '' }}</p>
                                    </div>
                                    <span class="badge badge-danger">{{ $match['match_score'] ?? 0 }}%</span>
                                </div>
                                @if(!empty($match['matched_fields']))
                                <p class="text-sm mt-2">Matched fields: {{ implode(', ', $match['matched_fields']) }}</p>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="5" class="text-center py-12 text-[--color-ink-muted]">No screening history found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
function toggleMatches(btn) {
    const row = btn.closest('tr').nextElementSibling;
    if (row.classList.contains('match-details-row')) {
        row.classList.toggle('hidden');
        btn.textContent = row.classList.contains('hidden') ? 'View Matches' : 'Hide Matches';
    }
}
</script>
@endpush
@endsection
