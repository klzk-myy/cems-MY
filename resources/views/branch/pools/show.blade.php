<x-app-layout title="Pool: {{ $branchPool->branch?->name }} - {{ $branchPool->currency_code }}">
    <div class="space-y-6">
        <x-page-header title="{{ $branchPool->branch?->name }} - {{ $branchPool->currency_code }}" :description="'Branch Pool'">
            <x-slot:actions>
                <x-button href="{{ route('branch-pools.index') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-card title="Available Balance">
                <p class="text-2xl font-bold">{{ number_format((float) $branchPool->available_balance, 4) }}</p>
            </x-card>
            <x-card title="Allocated Balance">
                <p class="text-2xl font-bold">{{ number_format((float) $branchPool->allocated_balance, 4) }}</p>
            </x-card>
            <x-card title="Total Balance">
                <p class="text-2xl font-bold">{{ number_format((float) ($branchPool->available_balance + $branchPool->allocated_balance), 4) }}</p>
            </x-card>
        </div>

        @if(auth()->user()?->role->value !== 'teller')
            <x-card title="Fund Pool">
                <form action="{{ route('branch-pools.fund', $branchPool->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="fund-amount" class="block text-sm font-medium mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="fund-amount" required class="w-full rounded-md border-border bg-surface text-ink text-sm">
                    </div>
                    <x-button type="submit" variant="success">Fund</x-button>
                </form>
            </x-card>

            <x-card title="Debit Pool">
                <form action="{{ route('branch-pools.debit', $branchPool->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="debit-amount" class="block text-sm font-medium mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="debit-amount" required class="w-full rounded-md border-border bg-surface text-ink text-sm">
                    </div>
                    <x-button type="submit" variant="danger">Debit</x-button>
                </form>
            </x-card>

            @if($branchPool->currency_code === \App\Models\Currency::baseCurrency() && $remitDestinations->isNotEmpty())
                <x-card title="Remit Funds">
                    <p class="text-sm text-muted mb-4">
                        Send {{ \App\Models\Currency::baseCurrency() }} {{ $branchPool->branch?->isHeadOffice() ? 'capital to a branch' : 'surplus to head office' }}.
                        The amount is held in transit until the receiving side acknowledges.
                    </p>
                    <form action="{{ route('branch-pools.remit', $branchPool->id) }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="remit-amount" class="block text-sm font-medium mb-1">Amount</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="remit-amount" required class="w-full rounded-md border-border bg-surface text-ink text-sm">
                            </div>
                            <div>
                                <label for="remit-to" class="block text-sm font-medium mb-1">Destination</label>
                                <select name="to_branch_id" id="remit-to" required class="w-full rounded-md border-border bg-surface text-ink text-sm">
                                    @foreach($remitDestinations as $destination)
                                        <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="remit-notes" class="block text-sm font-medium mb-1">Notes (optional)</label>
                            <input type="text" name="notes" id="remit-notes" maxlength="500" class="w-full rounded-md border-border bg-surface text-ink text-sm">
                        </div>
                        <x-button type="submit" variant="primary">Remit</x-button>
                    </form>
                </x-card>
            @endif
        @endif

        @if($pendingInbound->isNotEmpty() || $pendingOutbound->isNotEmpty())
            <x-card title="Remittances In Transit">
                <div class="space-y-4">
                    @foreach($pendingInbound as $remittance)
                        <div class="flex items-center justify-between gap-4 rounded-md border border-border p-3">
                            <div class="text-sm">
                                <span class="font-medium">{{ $remittance->remittance_number }}</span>
                                <span class="text-muted"> — {{ number_format((float) $remittance->amount, 4) }} {{ $remittance->currency_code }} from {{ $remittance->fromBranch?->name }} ({{ $remittance->initiator?->username }})</span>
                            </div>
                            <form action="{{ route('branch-pools.remittances.acknowledge', $remittance->id) }}" method="POST">
                                @csrf
                                <x-button type="submit" variant="success" size="sm">Acknowledge Receipt</x-button>
                            </form>
                        </div>
                    @endforeach

                    @foreach($pendingOutbound as $remittance)
                        <div class="flex items-center justify-between gap-4 rounded-md border border-border p-3">
                            <div class="text-sm">
                                <span class="font-medium">{{ $remittance->remittance_number }}</span>
                                <span class="text-muted"> — {{ number_format((float) $remittance->amount, 4) }} {{ $remittance->currency_code }} to {{ $remittance->toBranch?->name }} — awaiting acknowledgement</span>
                            </div>
                            <form action="{{ route('branch-pools.remittances.cancel', $remittance->id) }}" method="POST">
                                @csrf
                                <x-button type="submit" variant="danger" size="sm">Cancel</x-button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif

        @if($recentRemittances->isNotEmpty())
            <x-card title="Recent Remittances">
                <div class="space-y-2">
                    @foreach($recentRemittances as $remittance)
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span>
                                <span class="font-medium">{{ $remittance->remittance_number }}</span>
                                <span class="text-muted"> — {{ number_format((float) $remittance->amount, 4) }} {{ $remittance->currency_code }}: {{ $remittance->fromBranch?->name }} → {{ $remittance->toBranch?->name }}</span>
                            </span>
                            <span class="text-muted">{{ $remittance->status->label() }}</span>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
