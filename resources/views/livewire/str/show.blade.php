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
                <p class="text-sm font-medium text-[--color-ink]">{{ $str->transaction_date?->format('Y-m-d') ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Amount</p>
                <p class="text-sm font-medium text-[--color-ink]">MYR {{ number_format($str->amount ?? 0, 2) }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Currency</p>
                <p class="text-sm font-medium text-[--color-ink]">{{ $str->currency ?? 'MYR' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Transaction Type</p>
                <p class="text-sm font-medium text-[--color-ink]">{{ ucfirst(str_replace('_', ' ', $str->transaction_type ?? 'N/A')) }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Branch</p>
                <p class="text-sm font-medium text-[--color-ink]">{{ $str->branch->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Reported By</p>
                <p class="text-sm font-medium text-[--color-ink]">{{ $str->reporter->username ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[--color-border] p-6">
        <h2 class="text-lg font-semibold text-[--color-ink] mb-6">Customer Information</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Customer Name</p>
                <p class="text-sm font-medium text-[--color-ink]">{{ $str->customer_name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">IC/Passport Number</p>
                <p class="text-sm font-medium text-[--color-ink]">{{ $str->customer_ic ?? 'N/A' }}</p>
            </div>
            <div class="md:col-span-2">
                <p class="text-sm text-[--color-ink-muted] mb-1">Address</p>
                <p class="text-sm font-medium text-[--color-ink]">{{ $str->customer_address ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[--color-border] p-6">
        <h2 class="text-lg font-semibold text-[--color-ink] mb-6">Suspicious Activity Details</h2>

        <div class="space-y-6">
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-2">Description</p>
                <p class="text-sm text-[--color-ink]">{{ $str->description ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-2">Reasons for Suspicion</p>
                <ul class="list-disc list-inside text-sm text-[--color-ink] space-y-1">
                    <li>{{ $str->reasons ?? 'N/A' }}</li>
                </ul>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-[--color-ink-muted] mb-1">Risk Rating</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ ucfirst($str->risk_rating ?? 'medium') }}</span>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted] mb-1">Created At</p>
                    <p class="text-sm font-medium text-[--color-ink]">{{ $str->created_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>