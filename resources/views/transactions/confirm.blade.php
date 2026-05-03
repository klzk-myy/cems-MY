@extends('layouts.base')

@section('title', 'Confirm Transaction')

<div class="p-6">
    <div class="mb-6">
        <a href="{{ url()->previous() }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back</a>
    </div>

    <div class="max-w-lg mx-auto">
        <h1 class="text-2xl font-semibold mb-6">Confirm Transaction</h1>

        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
            <p class="text-[--color-text-muted] mb-6">Please review and confirm the transaction details below.</p>

            <div class="space-y-4 mb-6">
                <div class="flex justify-between p-3 bg-[--color-bg-tertiary] rounded-lg">
                    <span class="text-sm text-[--color-text-muted]">Transaction ID</span>
                    <span class="font-medium">#TXN-2024-001</span>
                </div>
                <div class="flex justify-between p-3 bg-[--color-bg-tertiary] rounded-lg">
                    <span class="text-sm text-[--color-text-muted]">Amount</span>
                    <span class="font-medium">$10,000.00</span>
                </div>
                <div class="flex justify-between p-3 bg-[--color-bg-tertiary] rounded-lg">
                    <span class="text-sm text-[--color-text-muted]">Currency</span>
                    <span class="font-medium">USD → MYR</span>
                </div>
                <div class="flex justify-between p-3 bg-[--color-bg-tertiary] rounded-lg">
                    <span class="text-sm text-[--color-text-muted]">Exchange Rate</span>
                    <span class="font-medium">4.7250</span>
                </div>
                <div class="flex justify-between p-3 bg-[--color-bg-tertiary] rounded-lg">
                    <span class="text-sm text-[--color-text-muted]">Recipient</span>
                    <span class="font-medium">John Doe</span>
                </div>
            </div>

            <div class="border-t border-[--color-border] pt-6 flex gap-4">
                <form action="{{ route('transactions.store') }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-[--color-accent] text-white rounded-lg hover:opacity-90">Confirm</button>
                </form>
                <a href="{{ url()->previous() }}" class="px-4 py-2 border border-[--color-border] rounded-lg hover:bg-[--color-bg-tertiary]">Cancel</a>
            </div>
        </div>
    </div>
</div>