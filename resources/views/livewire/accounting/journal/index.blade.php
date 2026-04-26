<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Journal Entries</h1>
        <p class="text-sm text-gray-500">Double-entry accounting records</p>
    </div>

    {{-- Header Actions --}}
    <div class="flex justify-end mb-6">
        <a href="{{ route('accounting.journal.create') }}" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Entry
        </a>
    </div>

    {{-- Filters --}}
    <div class="card mb-6">
        <div class="card-body">
            <form wire:submit="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="form-group mb-0">
                    <label class="form-label">Search</label>
                    <input type="text" wire:model="search" class="form-input" placeholder="Entry ID or description...">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Status</label>
                    <select wire:model="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Type</label>
                    <select wire:model="entryType" class="form-select">
                        <option value="">All Types</option>
                        @foreach($referenceTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
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

    {{-- Entries Table --}}
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Entry No.</th>
                        <th>Description</th>
                        <th>Accounts</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td>{{ $entry->entry_date?->format('d M Y') ?? 'N/A' }}</td>
                        <td class="font-mono text-xs">JE-{{ str_pad($entry->id, 6, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $entry->description }}</td>
                        <td class="text-gray-500">{{ $entry->lines->count() }} accounts</td>
                        <td class="font-mono">{{ number_format((float) $entry->getTotalDebits(), 2) }} MYR</td>
                        <td>
                            @php
                                $statusValue = $entry->status instanceof \App\Enums\JournalEntryStatus
                                    ? $entry->status->value
                                    : (string)$entry->status;
                                $statusLabel = $entry->status instanceof \App\Enums\JournalEntryStatus
                                    ? $entry->status->label()
                                    : (string)$entry->status;
                                $statusClass = match($statusValue) {
                                    'Posted' => 'badge-success',
                                    'Pending' => 'badge-warning',
                                    'Draft' => 'badge-default',
                                    'Reversed' => 'badge-info',
                                    default => 'badge-default'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        </td>
                        <td>
                            <a href="{{ route('accounting.journal.show', $entry->id) }}" class="btn btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-12 text-gray-500">No journal entries found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
            <div class="card-footer">
                {{ $entries->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
