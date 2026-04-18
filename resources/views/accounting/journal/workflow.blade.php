@extends('layouts.base')

@section('title', 'Journal Workflow')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Pending Approvals</h3></div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Entry</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingEntries ?? [] as $entry)
                    <tr>
                        <td class="font-mono">JE-{{ str_pad($entry->id, 6, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $entry->description }}</td>
                        <td class="font-mono">{{ number_format($entry->total_debit, 2) }}</td>
                        <td>
                            <form method="POST" action="/accounting/journal/{{ $entry->id }}/approve" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center py-8 text-[--color-ink-muted]">No pending entries</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Recent Activity</h3></div>
        <div class="card-body">
            @forelse($recentActivity ?? [] as $activity)
            <div class="border-l-2 border-[--color-border] pl-4 mb-3">
                <p class="text-sm">{{ $activity['description'] }}</p>
                <p class="text-xs text-[--color-ink-muted]">{{ $activity['time'] }}</p>
            </div>
            @empty
            <p class="text-[--color-ink-muted]">No recent activity</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
