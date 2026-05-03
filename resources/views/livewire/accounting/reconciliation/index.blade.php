<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Bank Reconciliation</h1>
        <p class="text-sm text-gray-500">Match bank statement transactions with internal records</p>
    </div>

    <div class="flex justify-end mb-6 gap-3">
        <a href="{{ route('accounting.reconciliation.report') }}" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            View Report
        </a>
        <button class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></svg>
            </svg>
            Import Statement
        </button>
    </div>

    <div class="card mb-6">
        <div class="card-body">
            <form wire:submit="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="form-group mb-0">
                    <label class="form-label">Search</label>
                    <input type="text" wire:model="search" class="form-input" placeholder="Reference or description...">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Status</label>
                    <select wire:model="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach($statusOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Account</label>
                    <select wire:model="accountCode" class="form-select">
                        <option value="">All Accounts</option>
                        @foreach($accountCodes as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Date From</label>
                    <input type="date" wire:model="dateFrom" class="form-input">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Date To</label>
                    <input type="date" wire:model="dateTo" class="form-input">
                </div>
                <div class="md:col-span-5 flex justify-end gap-3">
                    <button type="button" wire:click="clearFilters" class="btn btn-ghost">Clear Filters</button>
                    <button type="submit" class="btn btn-secondary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
            <p class="stat-card-label">Total Items</p>
            <p class="stat-card-value">{{ $reconciliations->total() }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-card-label">Matched</p>
            <p class="stat-card-value text-green-600">{{ $reconciliations->total() - collect($reconciliations->items())->filter(fn($i) => $i->status !== 'matched')->count() }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-card-label">Unmatched</p>
            <p class="stat-card-value text-amber-500">{{ collect($reconciliations->items())->filter(fn($i) => $i->status === 'unmatched')->count() }}</p>
        </div>
    </div>

    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Account</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reconciliations as $item)
                    <tr>
                        <td>{{ $item->statement_date?->format('d M Y') ?? 'N/A' }}</td>
                        <td class="font-mono text-xs">{{ $item->reference ?? 'N/A' }}</td>
                        <td class="max-w-xs truncate">{{ $item->description ?? 'N/A' }}</td>
                        <td class="font-mono">{{ $item->account_code ?? 'N/A' }}</td>
                        <td class="font-mono text-right">{{ number_format((float) ($item->debit ?? 0), 2) }}</td>
                        <td class="font-mono text-right">{{ number_format((float) ($item->credit ?? 0), 2) }}</td>
                        <td>
                            @php
                                $statusClass = match($item->status) {
                                    'matched' => 'badge-success',
                                    'unmatched' => 'badge-warning',
                                    'exception' => 'badge-danger',
                                    default => 'badge-default'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ ucfirst($item->status ?? 'unknown') }}</span>
                        </td>
                        <td>
                            @if($item->status === 'unmatched')
                                <button class="btn btn-ghost btn-sm">Match</button>
                            @endif
                            <a href="{{ route('accounting.reconciliation.report', ['statement_date' => $item->statement_date?->format('Y-m-d'), 'account_code' => $item->account_code]) }}" class="btn btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-12 text-gray-500">No reconciliation items found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reconciliations->hasPages())
            <div class="card-footer">
                {{ $reconciliations->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
