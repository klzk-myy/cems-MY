<x-app-layout title="Journal Entry">
    <div class="space-y-6">
        <x-page-header title="Journal Entry" description="Entry #{{ $entry->entry_number ?? 'JE-'.$entry->id }}">
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('accounting.journal') }}">Back</x-button>
                @can('reverse', $entry)
                    @if($entry->isPosted())
                        <x-button variant="danger" @click="$dispatch('open-reverse-modal')">Reverse</x-button>
                    @endif
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-ink-muted">Date</p>
                        <p class="mt-1 text-sm font-medium text-ink">{{ $entry->entry_date?->format('Y-m-d') ?? $entry->entry_date }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Reference</p>
                        <p class="mt-1 text-sm font-medium text-ink">
                            {{ $entry->reference_type?->value ?? $entry->reference_type }}{{ $entry->reference_id ? ' #'.$entry->reference_id : '' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Status</p>
                        <p class="mt-1">
                            <x-badge
                                :variant="match ($entry->status?->value) {
                                    'Posted' => 'success',
                                    'Pending' => 'warning',
                                    'Draft' => 'secondary',
                                    'Rejected' => 'danger',
                                    'Reversed' => 'info',
                                    default => 'secondary',
                                }"
                            >
                                {{ $entry->status?->label() ?? 'N/A' }}
                            </x-badge>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Branch</p>
                        <p class="mt-1 text-sm font-medium text-ink">{{ $entry->branch->name ?? 'Company-wide' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Created By</p>
                        <p class="mt-1 text-sm font-medium text-ink">{{ $entry->creator->username ?? 'System' }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-sm text-ink-muted">Description</p>
                    <p class="mt-1 text-sm font-medium text-ink">{{ $entry->description }}</p>
                </div>
            </div>
        </x-card>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @foreach($entry->lines as $line)
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono">{{ $line->account_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $line->account->account_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $line->description ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format((float) $line->debit, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format((float) $line->credit, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-canvas-subtle">
                        <td colspan="3" class="px-4 py-3 text-sm font-medium text-ink">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">{{ number_format((float) $entry->getTotalDebits(), 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">{{ number_format((float) $entry->getTotalCredits(), 2) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Audit Trail">
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-border">
                    <div>
                        <p class="text-sm text-ink">Created</p>
                        <p class="text-xs text-ink-muted">{{ $entry->creator->username ?? 'System' }}</p>
                    </div>
                    <p class="text-sm text-ink-muted">{{ $entry->created_at?->format('Y-m-d H:i:s') }}</p>
                </div>
                @if($entry->posted_at)
                    <div class="flex items-center justify-between py-2 border-b border-border">
                        <div>
                            <p class="text-sm text-ink">Posted</p>
                            <p class="text-xs text-ink-muted">{{ $entry->postedBy->username ?? 'System' }}</p>
                        </div>
                        <p class="text-sm text-ink-muted">{{ $entry->posted_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                @endif
                @if($entry->approved_at)
                    <div class="flex items-center justify-between py-2 border-b border-border">
                        <div>
                            <p class="text-sm text-ink">Approved</p>
                            <p class="text-xs text-ink-muted">{{ $entry->approver->username ?? 'System' }}</p>
                        </div>
                        <p class="text-sm text-ink-muted">{{ $entry->approved_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                @endif
                @if($entry->reversed_at)
                    <div class="flex items-center justify-between py-2 border-b border-border">
                        <div>
                            <p class="text-sm text-ink">Reversed</p>
                            <p class="text-xs text-ink-muted">{{ $entry->reversedBy->username ?? 'System' }}</p>
                        </div>
                        <p class="text-sm text-ink-muted">{{ $entry->reversed_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                @endif
            </div>
        </x-card>

        @can('reverse', $entry)
            @if($entry->isPosted())
                <div x-data="journalShow" @open-reverse-modal.window="open = true">
                    <div x-show="open" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                         @keydown.escape.window="open = false">
                        <div class="bg-surface rounded-lg shadow-xl w-full max-w-md" @click.outside="open = false">
                            <form action="{{ route('accounting.journal.reverse', $entry) }}" method="POST">
                                @csrf
                                <div class="p-6 space-y-4">
                                    <h3 class="text-lg font-semibold text-ink">Reverse Journal Entry</h3>
                                    <p class="text-sm text-ink-muted">A compensating entry will be posted to reverse this entry.</p>
                                    <x-textarea name="reason" label="Reason" rows="3" required maxlength="255" placeholder="Why is this entry being reversed?">{{ old('reason') }}</x-textarea>
                                </div>
                                <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-border">
                                    <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                                    <x-button type="submit" variant="danger">Reverse Entry</x-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endcan
    </div>
</x-app-layout>
