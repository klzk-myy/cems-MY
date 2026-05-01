@extends('layouts.base')

@section('title', 'Compliance Deadlines')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Upcoming Deadlines</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
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
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Overdue</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Upcoming</span>
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
