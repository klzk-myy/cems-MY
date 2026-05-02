@extends('layouts.base')

<div>
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <button wire:click="cancel" class="btn btn-ghost btn-icon">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Create Stock Transfer</h1>
                <p class="text-sm text-gray-500">Request a new inter-branch currency transfer</p>
            </div>
        </div>
    </div>

    <form wire:submit="save">
        {{-- Transfer Details --}}
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Transfer Details</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="form-label">Transfer Date</label>
                        <input
                            type="date"
                            wire:model.live="transferDate"
                            class="form-input {{ isset($errors['transferDate']) ? 'border-red-500' : '' }}"
                        >
                        @if(isset($errors['transferDate']))
                            <p class="text-red-500 text-sm mt-1">{{ $errors['transferDate'] }}</p>
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="form-label">Transfer Type</label>
                        <select wire:model.live="type" class="form-input">
                            <option value="Standard">Standard</option>
                            <option value="Emergency">Emergency</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Return">Return</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Source Branch</label>
                        <select
                            wire:model.live="sourceBranchId"
                            class="form-input {{ isset($errors['sourceBranchId']) ? 'border-red-500' : '' }}"
                        >
                            <option value="">Select Source Branch</option>
                            @foreach($availableBranches as $branch)
                                <option value="{{ $branch['id'] }}">{{ $branch['name'] }} ({{ $branch['code'] }})</option>
                            @endforeach
                        </select>
                        @if(isset($errors['sourceBranchId']))
                            <p class="text-red-500 text-sm mt-1">{{ $errors['sourceBranchId'] }}</p>
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="form-label">Destination Branch</label>
                        <select
                            wire:model.live="destinationBranchId"
                            class="form-input {{ isset($errors['destinationBranchId']) ? 'border-red-500' : '' }}"
                        >
                            <option value="">Select Destination Branch</option>
                            @foreach($availableBranches as $branch)
                                <option value="{{ $branch['id'] }}">{{ $branch['name'] }} ({{ $branch['code'] }})</option>
                            @endforeach
                        </select>
                        @if(isset($errors['destinationBranchId']))
                            <p class="text-red-500 text-sm mt-1">{{ $errors['destinationBranchId'] }}</p>
                        @endif
                    </div>
                    <div class="md:col-span-2 form-group">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea
                            wire:model.live="notes"
                            class="form-input"
                            rows="2"
                            placeholder="Add any notes for this transfer..."
                        ></textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Transfer Items --}}
        <div class="card mb-6">
            <div class="card-header flex justify-between items-center">
                <h3 class="card-title">Transfer Items</h3>
                <button type="button" wire:click="addItem" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Item
                </button>
            </div>
            <div class="card-body">
                @if(isset($errors['items']))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                        {{ $errors['items'] }}
                    </div>
                @endif

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Currency</th>
                                <th class="text-right">Quantity</th>
                                <th class="text-right">Rate (MYR)</th>
                                <th class="text-right">Value (MYR)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $index => $item)
                            <tr>
                                <td>
                                    <select
                                        wire:model.live="items.{{ $index }}.currency_code"
                                        class="form-input {{ isset($errors["items.{$index}.currency_code"]) ? 'border-red-500' : '' }}"
                                    >
                                        <option value="">Select Currency</option>
                                        @foreach($availableCurrencies as $currency)
                                            <option value="{{ $currency['code'] }}">{{ $currency['code'] }} - {{ $currency['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @if(isset($errors["items.{$index}.currency_code"]))
                                        <p class="text-red-500 text-sm mt-1">{{ $errors["items.{$index}.currency_code"] }}</p>
                                    @endif
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        wire:model.live="items.{{ $index }}.quantity"
                                        step="0.01"
                                        min="0"
                                        class="form-input text-right font-mono {{ isset($errors["items.{$index}.quantity"]) ? 'border-red-500' : '' }}"
                                        placeholder="0.00"
                                    >
                                    @if(isset($errors["items.{$index}.quantity"]))
                                        <p class="text-red-500 text-sm mt-1">{{ $errors["items.{$index}.quantity"] }}</p>
                                    @endif
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        wire:model.live="items.{{ $index }}.rate"
                                        step="0.0001"
                                        min="0"
                                        class="form-input text-right font-mono {{ isset($errors["items.{$index}.rate"]) ? 'border-red-500' : '' }}"
                                        placeholder="0.0000"
                                    >
                                    @if(isset($errors["items.{$index}.rate"]))
                                        <p class="text-red-500 text-sm mt-1">{{ $errors["items.{$index}.rate"] }}</p>
                                    @endif
                                </td>
                                <td class="text-right font-mono">
                                    @if(!empty($items[$index]['quantity']) && !empty($items[$index]['rate']))
                                        {{ number_format((float) bcmul($items[$index]['quantity'], $items[$index]['rate'], 4), 2) }}
                                    @else
                                        0.00
                                    @endif
                                </td>
                                <td>
                                    @if(count($items) > 1)
                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $index }})"
                                            class="btn btn-ghost btn-icon text-red-500 hover:text-red-700"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right font-medium">Total Value (MYR)</td>
                                <td class="text-right font-mono font-bold">{{ number_format((float) $totalValue, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <button type="button" wire:click="cancel" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Create Transfer
            </button>
        </div>
    </form>
</div>
