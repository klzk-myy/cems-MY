@extends('layouts.base')

@section('title', 'Batch Upload')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Transaction Batch Upload</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('transactions.batch-upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-6">
                <label class="form-label">CSV File</label>
                <input type="file" name="file" class="form-input" accept=".csv" required>
                <p class="text-sm text-[--color-ink-muted] mt-2">
                    Download template: <a href="#" class="text-primary hover:underline">transaction_template.csv</a>
                </p>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>

        @if(!empty($recentImports))
        <div class="mt-8">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Recent Imports</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Imported</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentImports as $import)
                    <tr>
                        <td>{{ $import['date'] ?? 'N/A' }}</td>
                        <td class="font-mono">{{ $import['filename'] ?? 'N/A' }}</td>
                        <td>
                            @if(isset($import['status']))
                                @statuslabel($import['status'])
                            @endif
                        </td>
                        <td class="font-mono">{{ $import['count'] ?? 0 }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection