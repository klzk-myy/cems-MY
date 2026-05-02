@extends('layouts.base')

@section('title', 'Journal Entry - CEMS-MY')

@section('content')
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Journal Entry {{ $entryData['entry_number'] }}</h1>
                <p class="text-sm text-gray-500">Double-entry accounting record</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="badge {{ $entryData['status_color'] }}">{{ $entryData['status_label'] }}</span>
                @if($entryData['is_reversed'])
                    <span class="badge badge-info">Reversed</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Entry Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Entry Information</h3>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Date:</dt>
                        <dd class="font-medium">{{ $entryData['entry_date'] }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Type:</dt>
                        <dd class="font-medium">{{ $entryData['reference_type'] ?? 'Manual' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Created:</dt>
                        <dd class="font-medium">{{ $entryData['created_at'] }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-medium text-gray-500 mb-2">People</h3>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Created By:</dt>
                        <dd class="font-medium">{{ $entryData['creator']['name'] ?? 'N/A' }}</dd>
                    </div>
                    @if($entryData['poster'])
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Posted By:</dt>
                        <dd class="font-medium">{{ $entryData['poster']['name'] ?? 'N/A' }}</dd>
                    </div>
                    @endif
                    @if($entryData['reverser'])
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Reversed By:</dt>
                        <dd class="font-medium">{{ $entryData['reverser']['name'] ?? 'N/A' }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Totals</h3>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Total Debits:</dt>
                        <dd class="font-mono font-medium">{{ number_format((float) $entryData['total_debits'], 2) }} MYR</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Total Credits:</dt>
                        <dd class="font-mono font-medium">{{ number_format((float) $entryData['total_credits'], 2) }} MYR</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Balanced:</dt>
                        <dd class="font-medium">
                            @if($entryData['is_balanced'])
                                <span class="text-green-600">Yes</span>
                            @else
                                <span class="text-red-600">No</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Description --}}
    <div class="card mb-6">
        <div class="card-body">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Description</h3>
            <p class="text-gray-900">{{ $entryData['description'] ?? 'No description' }}</p>
        </div>
    </div>

    {{-- Journal Lines --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Journal Lines</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Account Type</th>
                        <th class="text-right">Debit (MYR)</th>
                        <th class="text-right">Credit (MYR)</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $line)
                    <tr>
                        <td>
                            <div>
                                <span class="font-medium">{{ $line['account_code'] }}</span>
                                <span class="text-gray-500"> - {{ $line['account_name'] }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-default">{{ $line['account_type'] }}</span>
                        </td>
                        <td class="text-right font-mono {{ $line['is_debit'] ? '' : 'text-gray-500' }}">
                            {{ $line['is_debit'] ? number_format((float) $line['debit'], 2) : '-' }}
                        </td>
                        <td class="text-right font-mono {{ $line['is_credit'] ? '' : 'text-gray-500' }}">
                            {{ $line['is_credit'] ? number_format((float) $line['credit'], 2) : '-' }}
                        </td>
                        <td class="text-gray-500">{{ $line['description'] ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">No journal lines found</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200">
                        <td colspan="2" class="font-medium">Totals</td>
                        <td class="text-right font-mono font-medium">{{ number_format((float) $entryData['total_debits'], 2) }}</td>
                        <td class="text-right font-mono font-medium">{{ number_format((float) $entryData['total_credits'], 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex justify-between items-center">
        <a href="{{ route('accounting.journal') }}" class="btn btn-ghost">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Journal
        </a>

        @if($canReverse)
        <div class="flex items-center gap-3">
            <button type="button" wire:click="$set('showReverseModal', true)" class="btn btn-danger">
                Reverse Entry
            </button>
        </div>
        @endif
    </div>

    {{-- Reverse Modal --}}
    @if($showReverseModal ?? false)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('showReverseModal', false)">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Reverse Journal Entry</h3>
                <p class="text-sm text-gray-500 mb-4">
                    This will create a new reversing entry and mark this entry as reversed. This action cannot be undone.
                </p>
                <div class="form-group">
                    <label class="form-label">Reason for Reversal</label>
                    <textarea wire:model="reverseReason" class="form-input" rows="3" placeholder="Enter reason for reversal..."></textarea>
                    @error('reverse_reason')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-100 rounded-b-lg flex justify-end gap-3">
                <button type="button" wire:click="$set('showReverseModal', false)" class="btn btn-ghost">Cancel</button>
                <button type="button" wire:click="reverse(reverseReason)" class="btn btn-danger" @if($isLoading) disabled @endif>
                    @if($isLoading)
                        Reversing...
                    @else
                        Reverse Entry
                    @endif
                </button>
            </div>
        </div>
    </div>
    @endif
@endsection
