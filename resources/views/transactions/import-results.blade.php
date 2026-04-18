@extends('layouts.base')

@section('title', 'Import Results')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Import Results</h3></div>
    <div class="card-body">
        <div class="mb-6">
            <dl class="grid grid-cols-3 gap-4">
                <div class="p-4 bg-[--color-surface-elevated] rounded">
                    <dt class="text-sm text-[--color-ink-muted]">Total Rows</dt>
                    <dd class="text-2xl font-mono">{{ $import->total_rows ?? 0 }}</dd>
                </div>
                <div class="p-4 bg-[--color-surface-elevated] rounded">
                    <dt class="text-sm text-[--color-ink-muted]">Successful</dt>
                    <dd class="text-2xl font-mono text-green-600">{{ $import->successful ?? 0 }}</dd>
                </div>
                <div class="p-4 bg-[--color-surface-elevated] rounded">
                    <dt class="text-sm text-[--color-ink-muted]">Failed</dt>
                    <dd class="text-2xl font-mono text-red-600">{{ $import->failed ?? 0 }}</dd>
                </div>
            </dl>
        </div>

        @if(!empty($import->errors))
        <div class="mt-6">
            <h4 class="text-sm font-medium text-red-600 mb-4">Errors</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Field</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($import->errors as $error)
                    <tr>
                        <td class="font-mono">{{ $error['row'] ?? 'N/A' }}</td>
                        <td class="font-mono">{{ $error['field'] ?? 'N/A' }}</td>
                        <td class="text-red-600">{{ $error['message'] ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="mt-6">
            <a href="{{ route('transactions.batch.upload') }}" class="btn btn-secondary">Upload Another</a>
        </div>
    </div>
</div>
@endsection