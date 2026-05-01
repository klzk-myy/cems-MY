@extends('layouts.base')

@section('title', 'Report History')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Report History</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th>Report</th>
                    <th>Period</th>
                    <th>Generated</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports ?? [] as $report)
                <tr>
                    <td>{{ $report['name'] ?? 'N/A' }}</td>
                    <td>{{ $report['period'] ?? 'N/A' }}</td>
                    <td>{{ $report['created_at']->format('d M Y') }}</td>
                    <td>
                        @if(($report['status'] ?? '') === 'completed')
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span>
                        @endif
                    </td>
                    <td>
                        <a href="/reports/{{ $report['id'] }}/download" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Download</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No reports found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection