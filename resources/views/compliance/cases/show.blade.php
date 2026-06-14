<x-app-layout title="Cases">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-ink">Case Details</h1>
                    <p class="mt-1 text-sm text-ink-muted">{{ $case->case_number }}</p>
                </div>
                <a href="{{ route('compliance.cases.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Case Overview -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Title</label>
                    <p class="text-sm text-ink">{{ $case->title ?? $case->reference_number ?? 'Case Details' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Severity</label>
                    <p class="text-sm text-ink">{{ $case->severity?->label() }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Category</label>
                    <p class="text-sm text-ink">{{ $case->case_type?->label() ?? $case->category ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Customer</label>
                    <p class="text-sm text-ink">{{ $case->customer?->full_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Status</label>
                    @php
                        $statusColor = match ($case->status?->value) {
                            'Open' => 'bg-blue-100 text-blue-700',
                            'UnderReview' => 'bg-yellow-100 text-yellow-700',
                            'PendingApproval' => 'bg-purple-100 text-purple-700',
                            'Closed' => 'bg-green-100 text-green-700',
                            'Escalated' => 'bg-red-100 text-red-700',
                            default => 'bg-canvas-subtle text-gray-700',
                        };
                    @endphp
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $statusColor }}">
                        {{ $case->status?->label() }}
                    </span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Assigned To</label>
                    <p class="text-sm text-ink">{{ $case->assignee?->name ?? 'Unassigned' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Created</label>
                    <p class="text-sm text-ink">{{ $case->created_at->format('Y-m-d H:i:s') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Due Date</label>
                    <p class="text-sm text-ink">{{ $case->sla_deadline?->format('Y-m-d') }}</p>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Description</h3>
            <p class="text-sm text-gray-600">{{ $case->case_summary ?? 'No description provided.' }}</p>
        </div>

        <!-- Timeline -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Case Timeline</h3>
            <div class="space-y-4">
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-blue-500"></div>
                    <div>
                        <p class="text-sm font-medium text-ink">Case created</p>
                        <p class="text-xs text-ink-muted">{{ $case->created_at?->format('Y-m-d H:i:s') }} by {{ $case->creator?->name ?? $case->assignee?->name ?? 'System' }}</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-yellow-500"></div>
                    <div>
                        <p class="text-sm font-medium text-ink">Assigned to reviewer</p>
                        <p class="text-xs text-ink-muted">{{ $case->assignee?->name ?? 'Unassigned' }}</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-gray-300"></div>
                    <div>
                        <p class="text-sm font-medium text-ink">SLA deadline</p>
                        <p class="text-xs text-ink-muted">{{ $case->sla_deadline?->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Evidence -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Attached Evidence</h3>
            <ul class="space-y-2">
                <li class="flex items-center gap-2">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">transaction_history_2024.pdf</a>
                    <span class="text-xs text-ink-muted"> (245 KB)</span>
                </li>
                <li class="flex items-center gap-2">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">customer_kyc_verification.pdf</a>
                    <span class="text-xs text-ink-muted"> (128 KB)</span>
                </li>
            </ul>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Update Status
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    Add Note
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    Attach Evidence
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    Close Case
                </button>
            </div>
        </div>
    </div>
</x-app-layout>