<x-app-layout title="Cases">
    <div class="space-y-6">
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
                    <p class="text-sm text-ink">{{ $case->assignee?->username ?? 'Unassigned' }}</p>
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
                        <p class="text-xs text-ink-muted">{{ $case->created_at?->format('Y-m-d H:i:s') }} by {{ $case->creator?->username ?? $case->assignee?->username ?? 'System' }}</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-warning"></div>
                    <div>
                        <p class="text-sm font-medium text-ink">Assigned to reviewer</p>
                        <p class="text-xs text-ink-muted">{{ $case->assignee?->username ?? 'Unassigned' }}</p>
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
            @forelse($case->documents as $document)
                <div class="flex items-center gap-2 py-1">
                    <span class="text-sm text-ink">{{ $document->file_name }}</span>
                    <span class="text-xs text-ink-muted">
                        Uploaded {{ $document->uploaded_at?->format('Y-m-d') ?? 'unknown date' }}
                        @if($document->verified_at)
                            &middot; Verified {{ $document->verified_at->format('Y-m-d') }}
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-sm text-ink-muted">No documents attached to this case.</p>
            @endforelse
        </x-card>

        <x-card title="Actions">
            <div class="flex flex-wrap gap-3">
                @can('update', $case)
                    <x-button variant="primary">Update Status</x-button>
                @endcan
                @can('addNote', $case)
                    <x-button variant="secondary">Add Note</x-button>
                @endcan
            </div>
        </x-card>
    </div>
</x-app-layout>
