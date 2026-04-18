@extends('layouts.base')

@section('title', 'Compare Test Runs')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Compare Test Runs</h3>
        <a href="{{ route('test-results.statistics') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 gap-6">
            <div class="p-6 bg-[--color-surface-elevated] rounded">
                <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Run #{{ $run1->id ?? 'N/A' }}</h4>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Suite</dt>
                        <dd class="font-medium">{{ $run1->suite ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Date</dt>
                        <dd class="font-mono">{{ $run1->created_at ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Passed</dt>
                        <dd class="font-mono text-green-600">{{ $run1->passed ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Failed</dt>
                        <dd class="font-mono text-red-600">{{ $run1->failed ?? 0 }}</dd>
                    </div>
                </dl>
            </div>
            <div class="p-6 bg-[--color-surface-elevated] rounded">
                <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Run #{{ $run2->id ?? 'N/A' }}</h4>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Suite</dt>
                        <dd class="font-medium">{{ $run2->suite ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Date</dt>
                        <dd class="font-mono">{{ $run2->created_at ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Passed</dt>
                        <dd class="font-mono text-green-600">{{ $run2->passed ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Failed</dt>
                        <dd class="font-mono text-red-600">{{ $run2->failed ?? 0 }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection