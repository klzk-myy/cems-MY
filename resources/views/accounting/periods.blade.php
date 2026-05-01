@extends('layouts.base')

@section('title', 'Accounting Periods')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Accounting Periods</h3></div>
    <div class="overflow-x-auto">
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
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Closed</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                        @endif
                    </td>
                    <td>
                        @if(!$period->is_closed)
                            <form method="POST" action="/accounting/periods/{{ $period->id }}/close" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Close</button>
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