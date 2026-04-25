<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('compliance.cases.index') }}" class="btn btn-ghost btn-icon">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-semibold text-[--color-ink]">Case #{{ $case->id }}</h1>
                <p class="text-sm text-[--color-ink-muted]">{{ $case->case_type->label() ?? 'Unknown Type' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if($case->status !== 'Closed')
                @if(!$case->assignee)
                    <div class="flex items-center gap-2">
                        <select wire:model="selectedOfficer" class="form-select w-auto">
                            <option value="">Assign to...</option>
                            @foreach($this->availableOfficers as $officer)
                                <option value="{{ $officer['id'] }}">{{ $officer['username'] }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="assign($selectedOfficer)" class="btn btn-secondary" {{ !$selectedOfficer ? 'disabled' : '' }}>
                            Assign
                        </button>
                    </div>
                @endif
                <button type="button" wire:click="escalate()" class="btn btn-warning">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                    </svg>
                    Escalate
                </button>
                @if($case->canBeResolved())
                    <button type="button" wire:click="$set('showCloseModal', true)" class="btn btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Close Case
                    </button>
                @endif
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Case Details --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Case Details</h3>
                    @php
                        $statusClass = match($case->status->value ?? '') {
                            'Closed' => 'badge-success',
                            'Escalated' => 'badge-danger',
                            'UnderReview' => 'badge-info',
                            default => 'badge-warning'
                        };
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $case->status->label() ?? 'Open' }}</span>
                </div>
                <div class="card-body">
                    <p class="text-[--color-ink]">{{ $case->case_summary ?? 'No description provided.' }}</p>
                </div>
            </div>

            {{-- Customer --}}
            @if($case->customer)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Customer</h3>
                    <a href="{{ route('customers.show', $case->customer_id) }}" class="btn btn-ghost btn-sm">View Profile</a>
                </div>
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center font-semibold">
                            {{ substr($case->customer->full_name, 0, 1) }}
                        </div>
                        <div>
                            <p class="font-medium">{{ $case->customer->full_name }}</p>
                            <p class="text-sm text-[--color-ink-muted]">{{ $case->customer->ic_number ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Linked Alerts --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Linked Alerts</h3>
                    @if($case->customer && count($this->unlinkedAlerts) > 0)
                        <div class="flex items-center gap-2">
                            <select wire:model="selectedAlertToLink" class="form-select w-auto text-sm">
                                <option value="">Link alert...</option>
                                @foreach($this->unlinkedAlerts as $alert)
                                    <option value="{{ $alert['id'] }}">#{{ $alert['id'] }} - {{ Str::limit($alert['reason'], 30) }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="linkAlert($selectedAlertToLink)" class="btn btn-ghost btn-sm" {{ !$selectedAlertToLink ? 'disabled' : '' }}>
                                Link
                            </button>
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    @forelse($case->alerts as $alert)
                    <div class="border-l-2 border-[--color-border] pl-4 mb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">{{ $alert->type->label() ?? 'Unknown' }}</p>
                                <p class="text-sm text-[--color-ink-muted]">{{ $alert->reason }}</p>
                            </div>
                            <button type="button" wire:click="unlinkAlert({{ $alert->id }})" class="btn btn-ghost btn-icon text-[--color-danger]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-[--color-ink-muted] mt-1">
                            {{ $alert->created_at->diffForHumans() }}
                        </p>
                    </div>
                    @empty
                    <p class="text-[--color-ink-muted] text-sm">No alerts linked to this case</p>
                    @endforelse
                </div>
            </div>

            {{-- Notes --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Notes</h3>
                </div>
                <div class="card-body">
                    {{-- Add Note Form --}}
                    <div class="mb-4 p-4 bg-[--color-canvas-subtle] rounded-lg">
                        <textarea wire:model="newNote" class="form-input w-full" rows="2" placeholder="Add a note..."></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="isInternalNote" class="rounded">
                                <span>Internal note</span>
                            </label>
                            <button type="button" wire:click="addNote()" class="btn btn-secondary btn-sm" {{ !$newNote ? 'disabled' : '' }}>
                                Add Note
                            </button>
                        </div>
                    </div>

                    @forelse($case->notes->sortByDesc('created_at') as $note)
                    <div class="border-l-2 border-[--color-border] pl-4 mb-4">
                        <p class="text-sm">{{ $note->content }}</p>
                        <p class="text-xs text-[--color-ink-muted] mt-1">
                            {{ $note->author->username ?? 'System' }} - {{ $note->created_at->diffForHumans() }}
                            @if($note->is_internal)
                                <span class="ml-2 badge badge-default">Internal</span>
                            @endif
                        </p>
                    </div>
                    @empty
                    <p class="text-[--color-ink-muted] text-sm">No notes yet</p>
                    @endforelse
                </div>
            </div>

            {{-- Documents --}}
            @if($case->documents->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Documents</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-2">
                        @foreach($case->documents as $document)
                        <div class="flex items-center justify-between p-3 bg-[--color-canvas-subtle] rounded-lg">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span class="text-sm">{{ $document->file_name }}</span>
                            </div>
                            <a href="{{ Storage::url($document->file_path) }}" target="_blank" class="btn btn-ghost btn-icon">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Details --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">Details</h3></div>
                <div class="card-body space-y-3">
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Case Number</p>
                        <p class="text-sm font-mono">{{ $case->case_number ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Priority</p>
                        @php $priorityClass = match($case->priority->value ?? '') { 'Critical' => 'badge-danger', 'High' => 'badge-warning', 'Medium' => 'badge-info', default => 'badge-default' }; @endphp
                        <span class="badge {{ $priorityClass }}">{{ $case->priority->label() ?? 'Low' }}</span>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Assigned To</p>
                        <p class="text-sm font-medium">{{ $case->assignee->username ?? 'Unassigned' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Created</p>
                        <p class="text-sm">{{ $case->created_at->format('d M Y, H:i') }}</p>
                    </div>
                    @if($case->sla_deadline)
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">SLA Deadline</p>
                        <p class="text-sm {{ $case->sla_deadline->isPast() && $case->status !== 'Closed' ? 'text-[--color-danger]' : '' }}">
                            {{ $case->sla_deadline->format('d M Y, H:i') }}
                            @if($case->sla_deadline->isPast() && $case->status !== 'Closed')
                                <span class="badge badge-danger ml-1">Overdue</span>
                            @endif
                        </p>
                    </div>
                    @endif
                    @if($case->resolved_at)
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Resolved At</p>
                        <p class="text-sm">{{ $case->resolved_at->format('d M Y, H:i') }}</p>
                    </div>
                    @endif
                    @if($case->resolution)
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Resolution</p>
                        <p class="text-sm">{{ $case->resolution }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Timeline --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">Timeline</h3></div>
                <div class="card-body">
                    <div class="space-y-4">
                        <div class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-[--color-info]"></div>
                            <div>
                                <p class="text-sm font-medium">Case Created</p>
                                <p class="text-xs text-[--color-ink-muted]">{{ $case->created_at->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                        @if($case->sla_deadline)
                        <div class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-[--color-warning]"></div>
                            <div>
                                <p class="text-sm font-medium">SLA Deadline</p>
                                <p class="text-xs text-[--color-ink-muted]">{{ $case->sla_deadline->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                        @endif
                        @if($case->escalated_at)
                        <div class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-[--color-danger]"></div>
                            <div>
                                <p class="text-sm font-medium">Escalated</p>
                                <p class="text-xs text-[--color-ink-muted]">{{ $case->escalated_at->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                        @endif
                        @if($case->resolved_at)
                        <div class="flex gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-[--color-success]"></div>
                            <div>
                                <p class="text-sm font-medium">Resolved</p>
                                <p class="text-xs text-[--color-ink-muted]">{{ $case->resolved_at->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Close Case Modal --}}
    @if($showCloseModal ?? false)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" x-data="{ show: true }" x-show="show" x-on:click.self="show = false; $wire.showCloseModal = false">
        <div class="bg-[--color-surface] rounded-xl shadow-xl w-full max-w-md mx-4" x-show="show" x-on:click.stop>
            <div class="p-6 border-b border-[--color-border]">
                <h3 class="text-lg font-semibold">Close Case</h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="form-group">
                    <label class="form-label">Resolution</label>
                    <select wire:model="closeResolution" class="form-select">
                        <option value="">Select resolution...</option>
                        @foreach($caseResolutions as $resolution)
                            <option value="{{ $resolution->value }}">{{ $resolution->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea wire:model="closeNotes" class="form-input" rows="3" placeholder="Add resolution notes..."></textarea>
                </div>
            </div>
            <div class="p-6 border-t border-[--color-border] flex justify-end gap-3">
                <button type="button" wire:click="$set('showCloseModal', false)" class="btn btn-ghost">Cancel</button>
                <button type="button" wire:click="close($closeResolution, $closeNotes)" class="btn btn-primary" {{ !$closeResolution ? 'disabled' : '' }}>
                    Close Case
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
