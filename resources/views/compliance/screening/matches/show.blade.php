<x-app-layout title="Screening Match Review">
    <div class="space-y-6">
        <x-page-header
            title="Screening Match Review"
            description="Result #{{ $result->id }} - {{ ucfirst($result->result) }} at {{ round((float) $result->match_score * 100, 1) }}%"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.screening.matches.index') }}">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Customer">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Name</span>
                        <span class="text-ink">
                            @if ($result->customer)
                                <a href="{{ route('customers.show', $result->customer) }}" class="text-primary hover:underline">{{ $result->customer->full_name }}</a>
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Customer ID</span>
                        <span class="text-ink">#{{ $result->customer_id ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Screened Name</span>
                        <span class="text-ink">{{ $result->screened_name }}</span>
                    </div>
                    @if ($result->transaction)
                        <div class="flex justify-between">
                            <span class="text-ink-muted">Transaction</span>
                            <span class="text-ink">#{{ $result->transaction->id }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Screened At</span>
                        <span class="text-ink">{{ $result->created_at?->format('d M Y H:i') ?? 'N/A' }}</span>
                    </div>
                </div>
            </x-card>

            <x-card title="Match Details">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Match Score</span>
                        <span class="text-ink font-medium">{{ round((float) $result->match_score * 100, 1) }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Match Type</span>
                        <span class="text-ink">{{ ucfirst($result->match_type?->value ?? (string) $result->match_type) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Result</span>
                        <x-badge :variant="$result->result === 'block' ? 'danger' : 'warning'">
                            {{ ucfirst($result->result) }}
                        </x-badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Source</span>
                        @if ($result->source === 'adverse_media')
                            <x-badge variant="warning">Adverse Media</x-badge>
                        @else
                            <x-badge variant="info">Sanctions</x-badge>
                        @endif
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Matched Fields</span>
                        <span class="text-ink">{{ implode(', ', $result->matched_fields ?? []) ?: '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Disposition</span>
                        @if ($result->disposition)
                            <x-badge :variant="$result->disposition === 'confirmed' ? 'danger' : 'success'">
                                {{ ucfirst($result->disposition) }}
                            </x-badge>
                        @else
                            <x-badge variant="gray">Pending</x-badge>
                        @endif
                    </div>
                </div>
            </x-card>
        </div>

        @if ($result->adverseMediaEntry)
            <x-card title="Adverse Media Article">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Name</span>
                        <span class="text-ink">{{ $result->adverseMediaEntry->name }}</span>
                    </div>
                    @if ($result->adverseMediaEntry->alias)
                        <div class="flex justify-between">
                            <span class="text-ink-muted">Alias</span>
                            <span class="text-ink">{{ $result->adverseMediaEntry->alias }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Article</span>
                        <span class="text-ink">{{ $result->adverseMediaEntry->article_title }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Source</span>
                        <span class="text-ink">{{ $result->adverseMediaEntry->source }}</span>
                    </div>
                    @if ($result->adverseMediaEntry->url)
                        <div class="flex justify-between">
                            <span class="text-ink-muted">URL</span>
                            <a href="{{ $result->adverseMediaEntry->url }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline break-all">{{ $result->adverseMediaEntry->url }}</a>
                        </div>
                    @endif
                    @if ($result->adverseMediaEntry->published_at)
                        <div class="flex justify-between">
                            <span class="text-ink-muted">Published</span>
                            <span class="text-ink">{{ $result->adverseMediaEntry->published_at->format('d M Y') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Severity</span>
                        <x-badge :variant="$result->adverseMediaEntry->severity === 'high' ? 'danger' : ($result->adverseMediaEntry->severity === 'medium' ? 'warning' : 'gray')">
                            {{ ucfirst($result->adverseMediaEntry->severity) }}
                        </x-badge>
                    </div>
                    @if ($result->adverseMediaEntry->snippet)
                        <div class="flex justify-between gap-6">
                            <span class="text-ink-muted shrink-0">Snippet</span>
                            <span class="text-ink text-right">{{ $result->adverseMediaEntry->snippet }}</span>
                        </div>
                    @endif
                </div>
            </x-card>
        @endif

        @if (! $result->isAdverseMedia())
        <x-card title="Sanction Entry">
            @if ($result->sanctionEntry)
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Entity Name</span>
                        <span class="text-ink">{{ $result->sanctionEntry->entity_name }}</span>
                    </div>
                    @if ($result->sanctionEntry->aliases)
                        <div class="flex justify-between">
                            <span class="text-ink-muted">Aliases</span>
                            <span class="text-ink">{{ implode(', ', $result->sanctionEntry->aliases) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-ink-muted">List</span>
                        <span class="text-ink">
                            @if ($result->sanctionEntry->sanctionList)
                                {{ $result->sanctionEntry->sanctionList->name }} ({{ $result->sanctionEntry->sanctionList->list_type->value }})
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Entry</span>
                        <a href="{{ route('compliance.sanctions.entries.show', $result->sanctionEntry) }}" class="text-primary hover:underline">
                            View entry #{{ $result->sanctionEntry->id }}
                        </a>
                    </div>
                </div>
            @else
                <p class="text-sm text-ink-muted">No specific sanction entry recorded for this result.</p>
            @endif
        </x-card>
        @endif

        @if ($result->isPending())
            <x-card title="Disposition">
                <div x-data="{ openModal: null }" class="space-y-4">
                    <p class="text-sm text-ink-muted">
                        @if ($result->isAdverseMedia())
                            Confirming this adverse media match escalates an enhanced due diligence review alert. The customer is not frozen or blocked.
                        @else
                            Confirming this match freezes the customer, blocks their transactions and flags BNM FIU/IGP reporting per pd-00.md 27.6/27.7.
                        @endif
                    </p>

                    <div class="flex flex-wrap gap-3">
                        <x-button variant="danger" type="button" @click="openModal = 'confirm'">Confirm Match</x-button>
                        <x-button variant="secondary" type="button" @click="openModal = 'dismiss'">Dismiss</x-button>
                    </div>

                    <div x-show="openModal === 'confirm'" x-cloak @keydown.escape.window="openModal = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div class="bg-surface rounded-lg shadow-xl max-w-md w-full p-6 space-y-4" @click.outside="openModal = null">
                            <h3 class="text-lg font-semibold text-ink">Confirm {{ $result->isAdverseMedia() ? 'Adverse Media' : 'Sanctions' }} Match</h3>
                            <p class="text-sm text-ink-muted">
                                @if ($result->isAdverseMedia())
                                    This will escalate an EDD review alert for this customer. Funds are not frozen.
                                @else
                                    This will freeze the customer, block transactions and require FIU reporting within 24 hours. This action cannot be undone.
                                @endif
                            </p>
                            <form method="POST" action="{{ route('compliance.screening.matches.confirm', $result->id) }}" class="space-y-4">
                                @csrf
                                <x-textarea name="reason" label="Reason (required)" rows="3" placeholder="Explain why this match is confirmed..."></x-textarea>
                                @error('reason')<p class="text-sm text-danger-text">{{ $message }}</p>@enderror
                                <div class="flex justify-end gap-3">
                                    <x-button variant="secondary" type="button" @click="openModal = null">Cancel</x-button>
                                    <x-button variant="danger" type="submit">Confirm Match</x-button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div x-show="openModal === 'dismiss'" x-cloak @keydown.escape.window="openModal = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div class="bg-surface rounded-lg shadow-xl max-w-md w-full p-6 space-y-4" @click.outside="openModal = null">
                            <h3 class="text-lg font-semibold text-ink">Dismiss Screening Match</h3>
                            <p class="text-sm text-ink-muted">The match will be marked as dismissed with your reason recorded in the audit log.</p>
                            <form method="POST" action="{{ route('compliance.screening.matches.dismiss', $result->id) }}" class="space-y-4">
                                @csrf
                                <x-textarea name="reason" label="Reason (required)" rows="3" placeholder="Explain why this match is a false positive..."></x-textarea>
                                @error('reason')<p class="text-sm text-danger-text">{{ $message }}</p>@enderror
                                <div class="flex justify-end gap-3">
                                    <x-button variant="secondary" type="button" @click="openModal = null">Cancel</x-button>
                                    <x-button variant="primary" type="submit">Dismiss</x-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </x-card>
        @else
            <x-card title="Disposition">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Outcome</span>
                        <x-badge :variant="$result->disposition === 'confirmed' ? 'danger' : 'success'">
                            {{ ucfirst((string) $result->disposition) }}
                        </x-badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Reason</span>
                        <span class="text-ink">{{ $result->disposition_reason }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Decided By</span>
                        <span class="text-ink">User #{{ $result->dispositioned_by }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-muted">Decided At</span>
                        <span class="text-ink">{{ $result->dispositioned_at?->format('d M Y H:i') }}</span>
                    </div>
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
