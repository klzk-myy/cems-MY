<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[--color-ink]">Reconciliation Report</h1>
        <p class="text-sm text-[--color-ink-muted]">Detailed bank reconciliation analysis</p>
    </div>

    {{-- Filters --}}
    <div class="card mb-6">
        <div class="card-body">
            <form wire:submit="generateReport" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="form-group mb-0">
                    <label class="form-label">Statement Date</label>
                    <input type="date" wire:model="statementDate" class="form-input">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Account Code</label>
                    <input type="text" wire:model="accountCode" class="form-input" placeholder="e.g., 1000">
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <a href="{{ route('accounting.reconciliation') }}" class="btn btn-ghost">Back to List</a>
                </div>
            </form>
        </div>
    </div>

    @if($report)
        {{-- Report Summary --}}
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <p class="stat-card-label">Statement Date</p>
                <p class="stat-card-value">{{ $report['statement_date'] ?? 'N/A' }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Total Items</p>
                <p class="stat-card-value">{{ $report['total_items'] ?? 0 }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Matched</p>
                <p class="stat-card-value text-[--color-success]">{{ $report['matched_count'] ?? 0 }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Unmatched</p>
                <p class="stat-card-value text-[--color-warning]">{{ $report['unmatched_count'] ?? 0 }}</p>
            </div>
        </div>

        {{-- Financial Summary --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <p class="stat-card-label">Total Debits</p>
                <p class="stat-card-value font-mono">{{ number_format((float) ($report['total_debits'] ?? 0), 2) }} MYR</p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Total Credits</p>
                <p class="stat-card-value font-mono">{{ number_format((float) ($report['total_credits'] ?? 0), 2) }} MYR</p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Net Amount</p>
                <p class="stat-card-value font-mono {{ (float) ($report['net_amount'] ?? 0) >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                    {{ number_format((float) ($report['net_amount'] ?? 0), 2) }} MYR
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8">
            {{-- Matched Items --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-[--color-success]">Matched Items</h4>
                    <span class="text-sm text-[--color-ink-muted]">{{ count($matchedItems) }} items</span>
                </div>
                <div class="table-container max-h-96 overflow-y-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($matchedItems as $item)
                            <tr>
                                <td class="text-sm">{{ $item['statement_date'] ?? 'N/A' }}</td>
                                <td class="font-mono text-xs">{{ $item['reference'] ?? 'N/A' }}</td>
                                <td class="font-mono text-right">{{ number_format((float) abs($item['amount']), 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-[--color-ink-muted]">No matched items</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Unmatched Items --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-[--color-warning]">Unmatched Items</h4>
                    <span class="text-sm text-[--color-ink-muted]">{{ count($unmatchedItems) }} items</span>
                </div>
                <div class="table-container max-h-96 overflow-y-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th class="text-right">Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($unmatchedItems as $item)
                            <tr>
                                <td class="text-sm">{{ $item['statement_date'] ?? 'N/A' }}</td>
                                <td class="font-mono text-xs">{{ $item['reference'] ?? 'N/A' }}</td>
                                <td class="font-mono text-right {{ $item['amount'] >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                                    {{ number_format((float) $item['amount'], 2) }}
                                </td>
                                <td>
                                    <button class="btn btn-ghost btn-sm">Match</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-[--color-ink-muted]">No unmatched items</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Exceptions --}}
        @if(count($exceptions) > 0)
        <div class="card mt-6">
            <div class="card-header">
                <h4 class="font-semibold text-[--color-danger]">Exceptions</h4>
                <span class="text-sm text-[--color-ink-muted]">{{ count($exceptions) }} items</span>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-right">Amount</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($exceptions as $item)
                        <tr>
                            <td class="text-sm">{{ $item['statement_date'] ?? 'N/A' }}</td>
                            <td class="font-mono text-xs">{{ $item['reference'] ?? 'N/A' }}</td>
                            <td class="max-w-xs truncate">{{ $item['description'] ?? 'N/A' }}</td>
                            <td class="font-mono text-right {{ $item['amount'] >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                                {{ number_format((float) $item['amount'], 2) }}
                            </td>
                            <td class="text-sm text-[--color-danger]">{{ $item['notes'] ?? 'N/A' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Report Metadata --}}
        <p class="text-xs text-[--color-ink-muted] mt-4">
            Report generated at: {{ $report['generated_at'] ?? now()->toIso8601String() }}
        </p>
    @else
        <div class="card">
            <div class="card-body text-center py-12">
                <p class="text-[--color-ink-muted]">Select a statement date and account code to generate a report</p>
            </div>
        </div>
    @endif
</div>
