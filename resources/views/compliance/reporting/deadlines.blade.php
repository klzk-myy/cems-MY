@extends('layouts.base')

@section('title', 'Compliance Deadlines')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Upcoming Deadlines</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Report</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deadlines ?? [] as $deadline)
                <tr>
                    <td>{{ $deadline['name'] ?? 'N/A' }}</td>
                    <td>{{ $deadline['due_date'] ?? 'N/A' }}</td>
                    <td>
                        @if(($deadline['is_overdue'] ?? false))
                            <span class="badge badge-danger">Overdue</span>
                        @else
                            <span class="badge badge-warning">Upcoming</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center py-8 text-[--color-ink-muted]">No upcoming deadlines</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
