@extends('layouts.base')

@section('title', 'Test Results')

@section('content')
{{-- Latest Status Widget --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" x-data="{ status: null, loading: true }"
     x-init="fetch('{{ route('test-results.status') }}')
         .then(r => r.json())
         .then(d => { status = d; loading = false; })
         .catch(e => { loading = false; })">
    <div class="card">
        <div class="card-body text-center">
            <div x-show="loading" class="text-[--color-ink-muted]">Loading...</div>
            <template x-if="status && !loading">
                <div>
                    <div class="text-2xl font-bold" x-text="status.pass_rate + '%'"></div>
                    <div class="text-sm text-[--color-ink-muted]">Pass Rate</div>
                    <div class="text-xs text-[--color-ink-muted] mt-1" x-text="status.last_run"></div>
                </div>
            </template>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div class="text-2xl font-bold" x-text="status?.total_tests ?? '-'"></div>
            <div class="text-sm text-[--color-ink-muted]">Total Tests</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <span class="badge" :class="status?.status === 'passed' ? 'badge-success' : 'badge-danger'"
                  x-text="status?.status ?? 'unknown'"></span>
            <div class="text-sm text-[--color-ink-muted] mt-1">Latest Status</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body flex items-center justify-center gap-2">
            <form method="POST" action="{{ route('test-results.run') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Run Tests</button>
            </form>
            <a href="{{ route('test-results.statistics') }}" class="btn btn-secondary">Statistics</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Test History</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Suite</th>
                    <th>Status</th>
                    <th>Tests</th>
                    <th>Passed</th>
                    <th>Failed</th>
                    <th>Duration</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($testRuns ?? [] as $run)
                <tr>
                    <td><a href="{{ route('test-results.show', $run->id) }}" class="text-primary hover:underline">{{ $run->suite ?? 'N/A' }}</a></td>
                    <td>
                        @if(isset($run->status))
                            <span class="badge @if($run->status === 'passed') badge-success @else badge-danger @endif">
                                {{ $run->status }}
                            </span>
                        @endif
                    </td>
                    <td class="font-mono">{{ $run->total_tests ?? 0 }}</td>
                    <td class="font-mono text-green-600">{{ $run->passed ?? 0 }}</td>
                    <td class="font-mono text-red-600">{{ $run->failed ?? 0 }}</td>
                    <td class="font-mono">{{ number_format($run->duration ?? 0, 2) }}s</td>
                    <td class="font-mono">{{ $run->created_at ?? 'N/A' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-8 text-[--color-ink-muted]">No test runs found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection