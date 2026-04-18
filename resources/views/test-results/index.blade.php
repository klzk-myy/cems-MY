@extends('layouts.base')

@section('title', 'Test Results')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Test Results</h3></div>
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