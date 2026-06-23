<x-app-layout title="Cases">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header title="Case Details" description="{{ $case->case_number }}">
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.cases.index') }}">Back to List</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Overview">
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
                    <x-badge
                        :variant="match ($case->status?->value) {
                            'Open' => 'info',
                            'UnderReview' => 'warning',
                            'PendingApproval' => 'purple',
                            'Closed' => 'success',
                            'Escalated' => 'danger',
                            default => 'gray',
                        }"
                    >
                        {{ $case->status?->label() }}
                    </x-badge>
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
        </x-card>

        <x-card title="Description">
            <p class="text-sm text-ink-muted">{{ $case->case_summary ?? 'No description provided.' }}</p>
        </x-card>

        <x-card title="Case Timeline">
            <div class="space-y-4">
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-primary"></div>
                    <div>
                        <p class="text-sm font-medium text-ink">Case created</p>
                        <p class="text-xs text-ink-muted">{{ $case->created_at?->format('Y-m-d H:i:s') }} by {{ $case->creator?->name ?? $case->assignee?->name ?? 'System' }}</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-warning"></div>
                    <div>
                        <p class="text-sm font-medium text-ink">Assigned to reviewer</p>
                        <p class="text-xs text-ink-muted">{{ $case->assignee?->name ?? 'Unassigned' }}</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-ink-muted"></div>
                    <div>
                        <p class="text-sm font-medium text-ink">SLA deadline</p>
                        <p class="text-xs text-ink-muted">{{ $case->sla_deadline?->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </x-card>

        <x-card title="Attached Evidence">
            <ul class="space-y-2">
                <li class="flex items-center gap-2">
                    <a href="#" class="text-sm text-info-text hover:text-info">transaction_history_2024.pdf</a>
                    <span class="text-xs text-ink-muted"> (245 KB)</span>
                </li>
                <li class="flex items-center gap-2">
                    <a href="#" class="text-sm text-info-text hover:text-info">customer_kyc_verification.pdf</a>
                    <span class="text-xs text-ink-muted"> (128 KB)</span>
                </li>
            </ul>
        </x-card>

        <x-card title="Actions">
            <div class="flex flex-wrap gap-3">
                <x-button variant="primary">Update Status</x-button>
                <x-button variant="secondary">Add Note</x-button>
                <x-button variant="secondary">Attach Evidence</x-button>
                <x-button variant="secondary">Close Case</x-button>
            </div>
        </x-card>
    </div>
</x-app-layout>
