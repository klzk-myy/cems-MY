@extends('layouts.base')

@section('title', 'Batch Upload')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Transaction Batch Upload</h3></div>
    <div class="p-6">
        <form method="POST" action="{{ route('transactions.batch-upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-6">
                <label class="block text-sm font-medium text-[--color-ink] mb-1">CSV File</label>
                <input type="file" name="file" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" accept=".csv" required>
                <p class="text-sm text-[--color-ink-muted] mt-2">
                    Download template: <a href="#" class="text-[--color-accent] hover:underline">transaction_template.csv</a>
                </p>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">Upload</button>
            </div>
        </form>

        @if(!empty($recentImports))
        <div class="mt-8">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Recent Imports</h4>
            <table class="w-full text-sm">
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