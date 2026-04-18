@extends('layouts.base')

@section('title', 'Fiscal Years')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Fiscal Years</h3></div>
    <div class="table-container">
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
                            <span class="badge badge-default">Closed</span>
                        @else
                            <span class="badge badge-success">Open</span>
                        @endif
                    </td>
                    <td>
                        <a href="/accounting/fiscal-years/{{ $year->year_code }}" class="btn btn-ghost btn-sm">View</a>
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
