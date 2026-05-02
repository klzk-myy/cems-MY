@extends('layouts.base')

@section('title', 'Revaluation History - CEMS-MY')

@section('content')
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Revaluation History</h1>
        <p class="text-sm text-gray-500">Historical log of currency revaluation entries</p>
    </div>

    {{-- Filters --}}
    <div class="card mb-6">
        <div class="card-body">
            <form wire:submit="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="form-group mb-0">
                    <label class="form-label">Search</label>
                    <input type="text" wire:model="search" class="form-input" placeholder="Entry ID or currency...">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Currency</label>
                    <select wire:model="currencyCode" class="form-select">
                        <option value="">All Currencies</option>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency }}">{{ $currency }}</option>
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
                <div class="md:col-span-4 flex justify-end gap-3">
                    <button type="button" wire:click="clearFilters" class="btn btn-ghost">Clear Filters</button>
                    <button type="submit" class="btn btn-secondary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    {{-- History Table --}}
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Currency</th>
                        <th>Till ID</th>
                        <th>Old Rate</th>
                        <th>New Rate</th>
                        <th>Position Amount</th>
                        <th>Gain/Loss</th>
                        <th>Posted By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td>{{ $entry->revaluation_date?->format('d M Y') ?? 'N/A' }}</td>
                        <td class="font-mono font-medium">{{ $entry->currency_code }}</td>
                        <td class="font-mono text-sm">{{ $entry->till_id ?? 'MAIN' }}</td>
                        <td class="font-mono">{{ number_format((float) $entry->old_rate, 6) }}</td>
                        <td class="font-mono">{{ number_format((float) $entry->new_rate, 6) }}</td>
                        <td class="font-mono">{{ number_format((float) $entry->position_amount, 4) }}</td>
                        <td class="font-mono {{ (float) $entry->gain_loss_amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format((float) $entry->gain_loss_amount, 2) }} MYR
                        </td>
                        <td class="text-gray-500">{{ $entry->postedBy?->name ?? 'System' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-12 text-gray-500">No revaluation entries found</td>
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
@endsection
