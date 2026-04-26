<div>
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Account: {{ $accountInfo['account_code'] ?? '' }}</h1>
                <p class="text-sm text-gray-500">{{ $accountInfo['account_name'] ?? '' }}</p>
            </div>
            <a href="{{ route('accounting.ledger') }}" class="btn btn-ghost">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Chart of Accounts
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-gray-500">Opening Balance</p>
                <p class="text-xl font-mono font-semibold text-gray-900">
                    {{ number_format((float) ($accountInfo['opening_balance'] ?? '0'), 2) }} MYR
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-gray-500">Total Debits</p>
                <p class="text-xl font-mono font-semibold text-gray-900">
                    {{ number_format((float) ($accountInfo['total_debits'] ?? '0'), 2) }} MYR
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-gray-500">Total Credits</p>
                <p class="text-xl font-mono font-semibold text-gray-900">
                    {{ number_format((float) ($accountInfo['total_credits'] ?? '0'), 2) }} MYR
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-gray-500">Closing Balance</p>
                <p class="text-xl font-mono font-semibold text-gray-900">
                    {{ number_format((float) ($accountInfo['closing_balance'] ?? '0'), 2) }} MYR
                </p>
            </div>
        </div>
    </div>

    {{-- Date Filter --}}
    <div class="card mb-6">
        <div class="card-body">
            <form wire:submit="applyDateFilter" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="form-group mb-0">
                    <label class="form-label">Date From</label>
                    <input type="date" wire:model="dateFrom" class="form-input">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Date To</label>
                    <input type="date" wire:model="dateTo" class="form-input">
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="btn btn-secondary">Apply Filter</button>
                    <a href="{{ route('accounting.ledger.account', $accountCode) }}" class="btn btn-ghost">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Ledger Entries Table --}}
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Entry</th>
                        <th>Description</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledger as $entry)
                    <tr>
                        <td>{{ $entry['entry_date'] }}</td>
                        <td class="font-mono text-xs">
                            @if($entry['journal_entry_id'])
                                <a href="{{ route('accounting.journal.show', $entry['journal_entry_id']) }}"
                                   class="text-blue-600 hover:underline">
                                    JE-{{ str_pad($entry['journal_entry_id'], 6, '0', STR_PAD_LEFT) }}
                                </a>
                            @else
                                {{ $entry['id'] }}
                            @endif
                        </td>
                        <td>{{ $entry['description'] }}</td>
                        <td class="font-mono text-right">
                            {{ $entry['debit'] && $entry['debit'] > 0 ? number_format((float) $entry['debit'], 2) : '-' }}
                        </td>
                        <td class="font-mono text-right">
                            {{ $entry['credit'] && $entry['credit'] > 0 ? number_format((float) $entry['credit'], 2) : '-' }}
                        </td>
                        <td class="font-mono text-right font-medium">
                            {{ number_format((float) $entry['running_balance'], 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-12 text-gray-500">No ledger entries found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
