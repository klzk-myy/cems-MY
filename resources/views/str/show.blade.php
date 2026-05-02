@extends('layouts.base')

@section('title', 'STR Details')

@section('header-title')
    <h1 class="text-xl font-semibold text-[--color-ink]">STR-2026-001</h1>
@endsection

@section('header-actions')
    <a href="/str/1/edit" class="btn btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
        Edit
    </a>
@endsection

@section('content')
<div class="mb-6">
    <a href="/str" class="inline-flex items-center gap-2 text-sm text-[--color-ink-muted] hover:text-[--color-ink] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to STRs
    </a>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-xl border border-[--color-border] p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-[--color-ink]">Transaction Information</h2>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800">Pending Review</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Transaction Date</p>
                <p class="text-sm font-medium text-[--color-ink]">2026-05-03</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Amount</p>
                <p class="text-sm font-medium text-[--color-ink]">MYR 85,000.00</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Currency</p>
                <p class="text-sm font-medium text-[--color-ink]">MYR</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Transaction Type</p>
                <p class="text-sm font-medium text-[--color-ink]">Cash Deposit</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Branch</p>
                <p class="text-sm font-medium text-[--color-ink]">Kuala Lumpur Main</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Reported By</p>
                <p class="text-sm font-medium text-[--color-ink]">John Doe</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[--color-border] p-6">
        <h2 class="text-lg font-semibold text-[--color-ink] mb-6">Customer Information</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Customer Name</p>
                <p class="text-sm font-medium text-[--color-ink]">Ahmad bin Hassan</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">IC/Passport Number</p>
                <p class="text-sm font-medium text-[--color-ink]">701225-10-1234</p>
            </div>
            <div class="md:col-span-2">
                <p class="text-sm text-[--color-ink-muted] mb-1">Address</p>
                <p class="text-sm font-medium text-[--color-ink]">No. 123, Jalan Ampang, 55000 Kuala Lumpur</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[--color-border] p-6">
        <h2 class="text-lg font-semibold text-[--color-ink] mb-6">Suspicious Activity Details</h2>

        <div class="space-y-6">
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-2">Description</p>
                <p class="text-sm text-[--color-ink]">Customer made multiple cash deposits totaling MYR 85,000 within a short period. The transactions appear inconsistent with the customer's known profile and business activities.</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-2">Reasons for Suspicion</p>
                <ul class="list-disc list-inside text-sm text-[--color-ink] space-y-1">
                    <li>Structuring behavior - multiple deposits just below reporting threshold</li>
                    <li>Inconsistent customer profile</li>
                    <li>Unusual transaction pattern for this customer</li>
                </ul>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-[--color-ink-muted] mb-1">Risk Rating</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">High</span>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted] mb-1">Created At</p>
                    <p class="text-sm font-medium text-[--color-ink]">2026-05-03 14:30:00</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection