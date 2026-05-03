@extends('layouts.base')

@section('title', 'Export Customer Data')

<div class="p-6">
    <div class="mb-6">
        <a href="{{ url()->previous() }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back</a>
    </div>

    <div class="max-w-xl mx-auto">
        <h1 class="text-2xl font-semibold mb-6">Export Customer Data</h1>

        <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] p-6">
            <p class="text-[--color-text-muted] mb-6">Select the format and data range for the export.</p>

            <form action="{{ route('customers.export', $customer->id ?? 1) }}" method="POST">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">Export Format</label>
                    <select name="format" class="w-full px-3 py-2 border border-[--color-border] rounded-lg bg-[--color-bg-primary] focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30">
                        <option value="csv">CSV</option>
                        <option value="xlsx">Excel (XLSX)</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">Date Range</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-[--color-text-muted] mb-1">From</label>
                            <input type="date" name="from_date" class="w-full px-3 py-2 border border-[--color-border] rounded-lg bg-[--color-bg-primary] focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30">
                        </div>
                        <div>
                            <label class="block text-xs text-[--color-text-muted] mb-1">To</label>
                            <input type="date" name="to_date" class="w-full px-3 py-2 border border-[--color-border] rounded-lg bg-[--color-bg-primary] focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30">
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">Include Data</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="include_transactions" checked class="rounded border-[--color-border]">
                            <span class="text-sm">Transaction History</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="include_profile" checked class="rounded border-[--color-border]">
                            <span class="text-sm">Profile Information</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="include_documents" class="rounded border-[--color-border]">
                            <span class="text-sm">Uploaded Documents</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="flex-1 px-4 py-2 bg-[--color-accent] text-white rounded-lg hover:opacity-90">Generate Export</button>
                    <a href="{{ route('customers.history', $customer->id ?? 1) }}" class="px-4 py-2 border border-[--color-border] rounded-lg hover:bg-[--color-bg-tertiary]">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>