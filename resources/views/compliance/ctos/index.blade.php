@extends('layouts.base')

@section('title', 'CTOS Reports')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">CTOS Reports</h1>
    <p class="text-sm text-[--color-ink-muted]">Cash Transaction Reports to BNM</p>
</div>
@endsection

@section('content')
<div class="grid grid-cols-5 gap-4 mb-6">
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Total</p>
            <p class="text-2xl font-bold">{{ $summary['total'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Draft</p>
            <p class="text-2xl font-bold">{{ $summary['draft'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Submitted</p>
            <p class="text-2xl font-bold text-blue-600">{{ $summary['submitted'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Acknowledged</p>
            <p class="text-2xl font-bold text-green-600">{{ $summary['acknowledged'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Rejected</p>
            <p class="text-2xl font-bold text-red-600">{{ $summary['rejected'] }}</p>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="/compliance/ctos" class="flex gap-4 items-end flex-wrap">
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="Draft" {{ request('status') == 'Draft' ? 'selected' : '' }}>Draft</option>
                    <option value="Submitted" {{ request('status') == 'Submitted' ? 'selected' : '' }}>Submitted</option>
                    <option value="Acknowledged" {{ request('status') == 'Acknowledged' ? 'selected' : '' }}>Acknowledged</option>
                    <option value="Rejected" {{ request('status') == 'Rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div>
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-input">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/compliance/ctos" class="btn btn-ghost">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>CTOS Number</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                <tr>
                    <td class="font-mono">{{ $report['ctos_number'] ?? 'N/A' }}</td>
                    <td>{{ $report['customer_name'] ?? 'N/A' }}</td>
                    <td class="font-mono">RM {{ number_format($report['amount_local'] ?? 0, 2) }}</td>
                    <td>
                        @if(($report['transaction_type'] ?? '') === 'Buy')
                            <span class="badge badge-success">Buy</span>
                        @else
                            <span class="badge badge-info">Sell</span>
                        @endif
                    </td>
                    <td>
                        @switch($report['status'] ?? 'Draft')
                            @case('Draft')
                                <span class="badge badge-default">Draft</span>
                                @break
                            @case('Submitted')
                                <span class="badge badge-info">Submitted</span>
                                @break
                            @case('Acknowledged')
                                <span class="badge badge-success">Acknowledged</span>
                                @break
                            @case('Rejected')
                                <span class="badge badge-danger">Rejected</span>
                                @break
                            @default
                                <span class="badge badge-default">{{ $report['status'] }}</span>
                        @endswitch
                    </td>
                    <td>{{ isset($report['report_date']) ? \Carbon\Carbon::parse($report['report_date'])->format('d M Y') : 'N/A' }}</td>
                    <td>
                        <a href="/compliance/ctos/{{ $report['id'] }}" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-[--color-ink-muted]">No CTOS reports found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pagination['last_page'] > 1)
    <div class="card-footer flex justify-between items-center">
        <p class="text-sm text-[--color-ink-muted]">
            Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
        </p>
        <div class="flex gap-2">
            @if($pagination['current_page'] > 1)
                <a href="/compliance/ctos?{{ http_build_query(array_merge(request()->except('page'), ['page' => $pagination['current_page'] - 1])) }}" class="btn btn-ghost btn-sm">Previous</a>
            @endif
            @if($pagination['current_page'] < $pagination['last_page'])
                <a href="/compliance/ctos?{{ http_build_query(array_merge(request()->except('page'), ['page' => $pagination['current_page'] + 1])) }}" class="btn btn-ghost btn-sm">Next</a>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
