@extends('layouts.base')

@section('title', 'Accounting Periods')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Accounting Periods</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Period Code</th>
                    <th>Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periods ?? [] as $period)
                <tr>
                    <td class="font-mono">{{ $period->period_code }}</td>
                    <td>{{ $period->name }}</td>
                    <td>{{ $period->start_date->format('d M Y') }}</td>
                    <td>{{ $period->end_date->format('d M Y') }}</td>
                    <td>
                        @if($period->is_closed)
                            <span class="badge badge-default">Closed</span>
                        @else
                            <span class="badge badge-success">Open</span>
                        @endif
                    </td>
                    <td>
                        @if(!$period->is_closed)
                            <form method="POST" action="/accounting/periods/{{ $period->id }}/close" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">Close</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No periods</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
