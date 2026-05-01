@extends('layouts.base')

@section('title', 'Create Stock Transfer - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Create Stock Transfer</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Initiate an inter-branch currency transfer</p>
</div>

<div class="card">
    <div class="p-6">
        <form method="POST" action="{{ route('stock-transfers.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Source Branch</label>
                    <select name="source_branch_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="">Select branch</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Destination Branch</label>
                    <select name="destination_branch_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="">Select branch</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Currency</label>
                    <select name="currency_code" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="">Select currency</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Amount</label>
                    <input type="number" name="amount" step="0.01" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">Create Transfer</button>
                <a href="{{ route('stock-transfers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection