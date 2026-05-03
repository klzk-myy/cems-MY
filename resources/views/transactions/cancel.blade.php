@extends('layouts.base')

@section('title', 'Cancel Transaction')

<div class="p-6">
    <div class="mb-6">
        <a href="{{ url()->previous() }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back</a>
    </div>

    <div class="max-w-lg mx-auto">
        <h1 class="text-2xl font-semibold mb-6">Cancel Transaction</h1>

        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
            <p class="text-[--color-text-muted] mb-4">Are you sure you want to cancel this transaction? This action cannot be undone.</p>

            <div class="mb-6 p-4 bg-[--color-bg-tertiary] rounded-lg">
                <p class="text-sm text-[--color-text-muted]">Transaction ID</p>
                <p class="font-medium">#TXN-2024-001</p>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Reason for Cancellation</label>
                <textarea class="w-full px-3 py-2 border border-[--color-border] rounded-lg bg-[--color-bg-primary] focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" rows="3" placeholder="Enter cancellation reason..."></textarea>
            </div>

            <div class="flex gap-4">
                <form action="{{ route('transactions.destroy', $transaction->id ?? 1) }}" method="POST" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Confirm Cancellation</button>
                </form>
                <a href="{{ url()->previous() }}" class="px-4 py-2 border border-[--color-border] rounded-lg hover:bg-[--color-bg-tertiary]">Go Back</a>
            </div>
        </div>
    </div>
</div>