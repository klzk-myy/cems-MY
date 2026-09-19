<x-app-layout title="Customer Details">
    <div class="space-y-6" x-data="customerModals">
        <x-page-header title="Customer Details">
            {{ $customer->full_name ?? 'Customer Name' }}
            @if ($customer->is_frozen)
                <x-badge variant="danger">Frozen</x-badge>
            @endif
            @if ($customer->closed_at)
                <x-badge variant="gray">Closed</x-badge>
            @endif

            <x-slot:actions>
                @can('update', $customer)
                    <x-button variant="secondary" href="{{ route('customers.edit', $customer ?? 1) }}">
                        Edit
                    </x-button>
                @endcan
                @if (auth()->user()?->role->canPerform(\App\Enums\Permission::AccessCompliance))
                    @if ($customer->is_frozen)
                        <x-button variant="secondary" type="button" @click="showUnfreeze = true">
                            Unfreeze
                        </x-button>
                    @else
                        <x-button variant="warning" type="button" @click="showFreeze = true">
                            Freeze
                        </x-button>
                    @endif
                @endif
                @if (auth()->user()?->role->canPerform(\App\Enums\Permission::ManageCustomers) && ! $customer->closed_at)
                    <x-button variant="danger" type="button" @click="showClose = true">
                        Close Account
                    </x-button>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6">
                <x-card>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 rounded-full bg-canvas-subtle flex items-center justify-center text-xl font-bold">
                            {{ strtoupper(substr($customer->full_name ?? 'A', 0, 1)) }}
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold">{{ $customer->full_name ?? '-' }}</h2>
                            <x-risk-badge :customer="$customer" />
                        </div>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="text-ink-muted">ID</span>
                            <p class="font-medium">{{ $customer->id_type ?? 'IC' }}: {{ $customer->id_number_masked ?? '****' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">Nationality</span>
                            <p class="font-medium">{{ $customer->nationality ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">Email</span>
                            <p class="font-medium">{{ $customer->email ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">Phone</span>
                            <p class="font-medium">{{ $decryptedPhone ?: '-' }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card title="CDD Status">
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Level</span>
                            <span class="font-medium">{{ $customer->cdd_level?->label() ?? 'Standard' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Last Screened</span>
                            <span class="font-medium">{{ $customer->latestRiskSnapshot?->snapshot_date?->format('d M Y') ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Next Review</span>
                            <span class="font-medium">{{ $customer->latestRiskSnapshot?->next_screening_date?->format('d M Y') ?? '-' }}</span>
                        </div>
                    </div>
                </x-card>

                <x-card title="Screening">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-ink-muted">Last Screened</span>
                            <span class="font-medium">{{ $customer->sanctions_screened_at?->format('d M Y') ?? 'Never' }}</span>
                        </div>
                        <div class="flex gap-3 pt-1">
                            <a href="{{ route('compliance.screening.show', $customer) }}" class="text-primary hover:underline">Screening History</a>
                            <a href="{{ route('compliance.screening.matches.index', ['customer_id' => $customer->id]) }}" class="text-primary hover:underline">Pending Matches</a>
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <x-card>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">Recent Transactions</h2>
                        <x-button variant="ghost" size="sm" href="{{ route('transactions.index') }}">View All</x-button>
                    </div>

                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @forelse($customer->transactions as $transaction)
                                <tr class="hover:bg-canvas-subtle">
                                    <td class="px-4 py-3 text-sm">{{ $transaction->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $transaction->type?->value ?? $transaction->type }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $transaction->currency_code ?? 'MYR' }}</td>
                                    <td class="px-4 py-3 text-sm">RM {{ number_format($transaction->amount_myr ?? 0, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <x-badge variant="success">Completed</x-badge>
                                    </td>
                                </tr>
                            @empty
                                <x-empty-state message="No recent transactions." :colspan="5" />
                            @endforelse
                        </x-slot:tbody>
                    </x-table>
                </x-card>

                <x-card title="Compliance Summary">
                    <x-stat-grid cols="4">
                        <x-stat-card label="Total Txns" :value="$customerShowData['stats']['total_transactions'] ?? 0" />
                        <x-stat-card label="Total Value" value="RM {{ number_format($customerShowData['stats']['total_value'] ?? 0, 2) }}" />
                        <x-stat-card label="Alerts" :value="$customerShowData['stats']['alerts'] ?? 0" />
                        <x-stat-card label="STRs Filed" :value="$customerShowData['stats']['str_filed'] ?? 0" />
                    </x-stat-grid>
                </x-card>

                <x-card title="KYC Documents">
                    <div class="space-y-3">
                        @forelse($customer->documents as $document)
                            @php
                                $statusVariant = match ($document->status) {
                                    \App\Enums\CustomerDocumentStatus::Verified => 'success',
                                    \App\Enums\CustomerDocumentStatus::Rejected => 'danger',
                                    \App\Enums\CustomerDocumentStatus::Expired => 'warning',
                                    default => 'info',
                                };
                            @endphp
                            <div class="p-3 bg-canvas-subtle rounded-lg">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="space-y-0.5 text-sm min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ $document->document_type?->label() ?? $document->document_type }}</span>
                                            <x-badge :variant="$statusVariant">{{ ucfirst($document->status?->value ?? 'pending') }}</x-badge>
                                        </div>
                                        <div class="text-xs text-ink-muted">
                                            ID: {{ $customer->id_number_masked ?? '****' }}
                                            · Uploaded {{ $document->created_at->format('d M Y') }}
                                            · Expiry {{ $document->expiry_date?->format('d M Y') ?? '-' }}
                                        </div>
                                        @if ($document->rejection_reason)
                                            <div class="text-xs text-danger-text">Rejected: {{ $document->rejection_reason }}</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @can('download', $document)
                                            <a href="{{ route('kyc-documents.download', $document) }}" class="text-primary hover:underline text-sm">Download</a>
                                        @endcan
                                        @can('verify', $document)
                                            <form method="POST" action="{{ route('kyc-documents.verify', $document) }}">
                                                @csrf
                                                <x-button type="submit" variant="secondary" size="sm">Verify</x-button>
                                            </form>
                                        @endcan
                                        @can('reject', $document)
                                            <details class="relative">
                                                <summary class="inline-flex cursor-pointer list-none"><x-button variant="danger" size="sm">Reject</x-button></summary>
                                                <form method="POST" action="{{ route('kyc-documents.reject', $document) }}" class="mt-2 space-y-2 w-64 p-3 bg-surface rounded-lg border border-canvas-subtle">
                                                    @csrf
                                                    <x-textarea name="reason" label="Rejection reason" rows="2" placeholder="State the reason for rejection..." required>{{ old('reason') }}</x-textarea>
                                                    <x-button type="submit" variant="danger" size="sm">Confirm Rejection</x-button>
                                                </form>
                                            </details>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-ink-muted">No KYC documents uploaded.</p>
                        @endforelse
                    </div>
                </x-card>

                <x-card title="Notes">
                    <div class="space-y-3">
                        @forelse($notes ?? [] as $note)
                            <div class="p-3 bg-canvas-subtle rounded-lg">
                                <div class="text-sm">{{ $note->note }}</div>
                                <div class="text-xs text-ink-muted mt-1">{{ $note->created_at->format('d M Y h:i A') }} - {{ $note->creator?->name ?? 'System' }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-ink-muted">No notes yet.</p>
                        @endforelse
                    </div>
                    @can('createNote', $customer)
                        <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mt-4">
                            @csrf
                            <x-textarea name="note" label="Add a note" rows="2" placeholder="Add a note...">{{ old('note') }}</x-textarea>
                            <x-button type="submit" variant="primary" size="sm">Add Note</x-button>
                        </form>
                    @endcan
                </x-card>
            </div>
        </div>

        @if (auth()->user()?->role->canPerform(\App\Enums\Permission::AccessCompliance) && ! $customer->is_frozen)
            <div x-show="showFreeze" x-cloak @keydown.escape.window="showFreeze = false" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="showFreeze = false"></div>
                <div x-show="showFreeze"
                     x-transition
                     class="relative bg-surface border border-border rounded-xl shadow-lg w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-ink mb-1">Freeze Customer</h3>
                    <p class="text-sm text-ink-muted mb-4">
                        Freeze {{ $customer->full_name }} to block all activity pending compliance review.
                    </p>
                    <form method="POST" action="{{ route('customers.freeze', $customer) }}">
                        @csrf
                        <x-textarea name="reason" label="Reason" rows="3" placeholder="State the reason for freezing..." required>{{ old('reason') }}</x-textarea>
                        <div class="flex justify-end gap-3 mt-4">
                            <x-button variant="secondary" type="button" @click="showFreeze = false">Cancel</x-button>
                            <x-button variant="warning" type="submit">Freeze Customer</x-button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="showUnfreeze" x-cloak @keydown.escape.window="showUnfreeze = false" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="showUnfreeze = false"></div>
                <div x-show="showUnfreeze"
                     x-transition
                     class="relative bg-surface border border-border rounded-xl shadow-lg w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-ink mb-1">Unfreeze Customer</h3>
                    <p class="text-sm text-ink-muted mb-4">
                        Restore normal activity for {{ $customer->full_name }}?
                    </p>
                    <form method="POST" action="{{ route('customers.unfreeze', $customer) }}">
                        @csrf
                        <div class="flex justify-end gap-3">
                            <x-button variant="secondary" type="button" @click="showUnfreeze = false">Cancel</x-button>
                            <x-button variant="primary" type="submit">Unfreeze Customer</x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if (auth()->user()?->role->canPerform(\App\Enums\Permission::ManageCustomers) && ! $customer->closed_at)
            <div x-show="showClose" x-cloak @keydown.escape.window="showClose = false" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="showClose = false"></div>
                <div x-show="showClose"
                     x-transition
                     class="relative bg-surface border border-border rounded-xl shadow-lg w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-danger-text mb-1">Close Customer Account</h3>
                    <p class="text-sm text-ink-muted mb-4">
                        Permanently close the account for {{ $customer->full_name }}. This cannot be undone from this screen.
                        Accounts with transactions pending approval or cancellation cannot be closed.
                    </p>
                    <form method="POST" action="{{ route('customers.close', $customer) }}">
                        @csrf
                        <x-textarea name="reason" label="Closure reason" rows="3" placeholder="State the reason for closure..." required>{{ old('reason') }}</x-textarea>
                        <div class="flex justify-end gap-3 mt-4">
                            <x-button variant="secondary" type="button" @click="showClose = false">Cancel</x-button>
                            <x-button variant="danger" type="submit">Close Account</x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
