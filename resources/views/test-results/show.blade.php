@extends('layouts.base')

@section('title', 'Test Result Details')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">{{ $testResult->suite ?? 'N/A' }} - Run #{{ $testResult->id ?? 'N/A' }}</h3>
        <a href="{{ route('test-results.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                <dd>
                    <span class="badge @if($testResult->status === 'passed') badge-success @else badge-danger @endif">
                        {{ $testResult->status ?? 'N/A' }}
                    </span>
                </dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Total Tests</dt>
                <dd class="text-2xl font-mono">{{ $testResult->total_tests ?? 0 }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Passed</dt>
                <dd class="text-2xl font-mono text-green-600">{{ $testResult->passed ?? 0 }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Failed</dt>
                <dd class="text-2xl font-mono text-red-600">{{ $testResult->failed ?? 0 }}</dd>
            </div>
        </div>

        @if(!empty($testResult->failures))
        <div class="mt-6">
            <h4 class="text-sm font-medium text-red-600 mb-4">Failures</h4>
            @foreach($testResult->failures as $failure)
            <div class="p-4 bg-red-50 border border-red-200 rounded mb-4">
                <p class="font-medium text-red-700">{{ $failure['test'] ?? 'Unknown Test' }}</p>
                <pre class="text-sm text-red-600 mt-2">{{ $failure['message'] ?? '' }}</pre>
            </div>
            @endforeach
        </div>
        @endif

        @if($previousRun)
        <div class="mt-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Comparison with Previous Run</h4>
            <p>Previous: {{ $previousRun->passed ?? 0 }}/{{ $previousRun->total_tests ?? 0 }} passed</p>
        </div>
        @endif
    </div>
</div>
@endsection