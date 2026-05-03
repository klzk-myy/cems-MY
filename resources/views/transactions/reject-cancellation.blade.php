@extends('layouts.base')

@section('title', 'Reject Cancellation')

<div class="p-6">
    <div class="mb-6">
        <a href="{{ url()->previous() }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back</a>
    </div>

    <div class="max-w-lg mx-auto">
        <h1 class="text-2xl font-semibold mb-6">Reject Cancellation Request</h1>

        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
            <p class="text-[--color-text-muted] mb-4">You are about to reject a cancellation request. Please provide a reason.</p>

            <div class="mb-6 p-4 bg-[--color-bg-tertiary] rounded-lg space-y-2">
                <div class="flex justify-between">
                    <span class="text-sm text-[--color-text-muted]">Transaction ID</span>
                    <span class="font-medium">#TXN-2024-001</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-[--color-text-muted]">Amount</span>
                    <span class="font-medium">$5,000.00</span>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Rejection Reason</label>
                <textarea class="w-full px-3 py-2 border border-[--color-border] rounded-lg bg-[--color-bg-primary] focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" rows="3" placeholder="Enter rejection reason..."></textarea>
            </div>

            <div class="flex gap-4">
                <form action="{{ route('transactions.reject-cancellation', $transaction->id ?? 1) }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Reject Cancellation</button>
                </form>
                <a href="{{ url()->previous() }}" class="px-4 py-2 border border-[--color-border] rounded-lg hover:bg-[--color-bg-tertiary]">Cancel</a>
            </div>
        </div>
    </div>
</div>