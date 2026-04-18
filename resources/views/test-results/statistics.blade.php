@extends('layouts.base')

@section('title', 'Test Statistics')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Test Statistics</h3>
        <a href="{{ route('test-results.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Total Runs</dt>
                <dd class="text-2xl font-mono">{{ $statistics['total_runs'] ?? 0 }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Average Duration</dt>
                <dd class="text-2xl font-mono">{{ number_format($statistics['avg_duration'] ?? 0, 2) }}s</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Success Rate</dt>
                <dd class="text-2xl font-mono @if(($statistics['success_rate'] ?? 0) >= 90) text-green-600 @else text-red-600 @endif">
                    {{ number_format($statistics['success_rate'] ?? 0, 1) }}%
                </dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Total Tests</dt>
                <dd class="text-2xl font-mono">{{ $statistics['total_tests'] ?? 0 }}</dd>
            </div>
        </div>

        <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Latest Results by Suite</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Suite</th>
                    <th>Last Run</th>
                    <th>Status</th>
                    <th class="text-right">Passed</th>
                    <th class="text-right">Failed</th>
                    <th class="text-right">Duration</th>
                </tr>
            </thead>
            <tbody>
                @forelse($latestBySuite ?? [] as $suite => $result)
                <tr>
                    <td class="font-medium">{{ $suite }}</td>
                    <td class="font-mono">{{ $result['date'] ?? 'N/A' }}</td>
                    <td>
                        <span class="badge @if(($result['status'] ?? '') === 'passed') badge-success @else badge-danger @endif">
                            {{ $result['status'] ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="font-mono text-right text-green-600">{{ $result['passed'] ?? 0 }}</td>
                    <td class="font-mono text-right text-red-600">{{ $result['failed'] ?? 0 }}</td>
                    <td class="font-mono text-right">{{ number_format($result['duration'] ?? 0, 2) }}s</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection