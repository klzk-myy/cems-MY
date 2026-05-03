@extends('layouts.base')

@section('title', 'Batch Upload Transactions')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Batch Upload</h3>
    </div>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-[--color-ink] mb-2">Batch Upload Transactions</h1>
            <p class="text-[--color-ink-muted]">Upload a CSV file to process multiple transactions at once.</p>
        </div>

        <form method="POST" action="{{ route('transactions.batch-upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-6">
                <label class="block text-sm font-medium text-[--color-ink] mb-2">CSV File</label>
                <input type="file" name="file" accept=".csv" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                @error('file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6 p-4 bg-[--color-surface-elevated] rounded-lg">
                <h4 class="text-sm font-medium text-[--color-ink] mb-2">CSV Format</h4>
                <p class="text-sm text-[--color-ink-muted] mb-2">Required columns: type, currency, amount, rate, customer_id</p>
                <code class="text-xs bg-[--color-canvas] px-2 py-1 rounded">type,currency,amount,rate,customer_id</code>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">
                    Upload & Process
                </button>
                <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">
                    Back
                </a>
            </div>
        </form>
    </div>
</div>
@endsection