@extends('layouts.base')

@section('title', 'Fiscal Years')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Fiscal Years</h3></div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Year Code</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fiscalYears ?? [] as $year)
                <tr>
                    <td class="font-mono font-medium">{{ $year->year_code }}</td>
                    <td>{{ $year->start_date->format('d M Y') }}</td>
                    <td>{{ $year->end_date->format('d M Y') }}</td>
                    <td>
                        @if($year->is_closed)
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Closed</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                        @endif
                    </td>
                    <td>
                        <a href="/accounting/fiscal-years/{{ $year->year_code }}" class="px-3 py-1.5 text-xs font-medium rounded-lg hover:bg-[--color-canvas-subtle]">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No fiscal years</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection