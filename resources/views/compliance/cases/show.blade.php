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
                    <p class="text-sm text-ink"><x-customer-link :customer="$case->customer" /></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Status</label>
                    <x-badge
                        :variant="match ($case->status) {
                            \App\Enums\ComplianceCaseStatus::Open => 'info',
                            \App\Enums\ComplianceCaseStatus::UnderReview => 'warning',
                            \App\Enums\ComplianceCaseStatus::PendingApproval => 'purple',
                            \App\Enums\ComplianceCaseStatus::Closed => 'success',
                            \App\Enums\ComplianceCaseStatus::Escalated => 'danger',
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

        <x-card title="Case Notes">
            @can('addNote', $case)
                <form method="POST" action="{{ route('compliance.cases.notes.store', $case) }}" class="mb-4">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <x-select name="note_type" :options="$noteTypes" label="Type" required inline />
                        <div class="md:col-span-2">
                            <x-textarea name="content" label="Note" placeholder="Add a note..." required :rows="1" inline />
                        </div>
                        <div class="flex items-end gap-4 pb-1">
                            <input type="hidden" name="is_internal" value="0">
                            <x-checkbox name="is_internal" label="Internal" :checked="true" inline />
                            <x-button variant="primary" type="submit">Add Note</x-button>
                        </div>
                    </div>
                </form>
            @endcan

            <div class="space-y-3">
                @forelse ($case->notes->sortByDesc('created_at') as $note)
                    <div class="flex gap-3">
                        <x-badge variant="gray">{{ $note->note_type?->label() }}</x-badge>
                        <div>
                            <p class="text-sm text-ink">{{ $note->content }}</p>
                            <p class="text-xs text-ink-muted">
                                {{ $note->author?->username ?? 'System' }} &middot; {{ $note->created_at?->format('Y-m-d H:i:s') }}
                                @unless ($note->is_internal)
                                    &middot; External
                                @endunless
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-ink-muted">No notes on this case yet.</p>
                @endforelse
            </div>
        </x-card>

        <x-card title="Actions">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                @can('update', $case)
                    @if ($statusOptions->isNotEmpty())
                        <form method="POST" action="{{ route('compliance.cases.update', $case) }}">
                            @csrf
                            @method('PATCH')
                            <x-select name="status" label="Status" :options="$statusOptions" required />
                            <x-select name="resolution" label="Resolution" :options="$resolutionOptions" placeholder="Required when closing" help="Required when moving the case to Closed." />
                            <x-input name="notes" label="Notes" placeholder="Optional notes" />
                            <x-button variant="primary" type="submit">Update Status</x-button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('compliance.cases.update', $case) }}">
                        @csrf
                        @method('PATCH')
                        <x-select name="assigned_to" label="Assigned Officer" :options="$officers" :value="$case->assigned_to" required />
                        <x-button variant="secondary" type="submit">Reassign</x-button>
                    </form>

                    @if ($case->status?->canMoveTo(\App\Enums\ComplianceCaseStatus::Escalated))
                        <form method="POST" action="{{ route('compliance.cases.escalate', $case) }}" data-confirm="Escalate this case?">
                            @csrf
                            <div class="flex h-full items-end">
                                <x-button variant="danger" type="submit">Escalate Case</x-button>
                            </div>
                        </form>
                    @endif
                @endcan
            </div>
        </x-card>
    </div>
</x-app-layout>
