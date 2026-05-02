@extends('layouts.base')

<div>
    <div class="card-header">
        <h3 class="card-title">Step 1: Select Customer</h3>
        <p class="text-sm text-gray-500">Search for an existing customer or create a new one</p>
    </div>
    <div class="card-body">
        {{-- Customer Search --}}
        <div class="form-group">
            <label class="form-label">Search Customer</label>
            <div class="relative">
                <input type="text"
                       wire:model.live.debounce.300ms="customerSearch"
                       class="form-input pr-10"
                       placeholder="Type customer name or IC number to search...">
                @if($customerSearch)
                    <button type="button"
                            wire:click="clearCustomer"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Search Results Dropdown --}}
            @if($showCustomerResults && $searchResults->isNotEmpty())
                <div class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-80 overflow-y-auto">
                    @foreach($searchResults as $customer)
                        <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                             wire:click="selectCustomer({{ $customer->id }})">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ $customer->full_name }}</span>
                                    @if($customer->pep_status)
                                        <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded">PEP</span>
                                    @endif
                                    @if($customer->sanction_hit)
                                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">Sanction</span>
                                    @endif
                                </div>
                                <span class="text-xs px-2 py-1 rounded
                                    @if($customer->risk_rating === 'High') bg-red-100 text-red-700
                                    @elseif($customer->risk_rating === 'Medium') bg-yellow-100 text-yellow-700
                                    @else bg-green-100 text-green-700
                                    @endif">
                                    {{ $customer->risk_rating ?? 'Unknown' }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $customer->ic_number ?? 'N/A' }}
                                @if($customer->nationality)
                                    &bull; {{ $customer->nationality }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($showCustomerResults && $searchResults->isEmpty() && strlen($customerSearch) >= 2)
                <div class="p-4 text-center text-gray-500">
                    No customers found matching "{{ $customerSearch }}"
                </div>
            @endif
        </div>

        {{-- Selected Customer Info --}}
        @if($selectedCustomer)
            <div class="mt-6 p-4 bg-gray-100 rounded-lg border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-lg">{{ $selectedCustomer->full_name }}</p>
                        <p class="text-sm text-gray-500">
                            {{ $selectedCustomer->ic_number ?? 'N/A' }}
                            @if($selectedCustomer->nationality)
                                &bull; {{ $selectedCustomer->nationality }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Risk Badge --}}
                        <span class="badge
                            @if($selectedCustomer->risk_rating === 'High') bg-red-100 text-red-800
                            @elseif($selectedCustomer->risk_rating === 'Medium') bg-yellow-100 text-yellow-800
                            @else bg-green-100 text-green-800
                            @endif">
                            {{ $selectedCustomer->risk_rating ?? 'Unknown' }} Risk
                        </span>

                        {{-- CDD Badge --}}
                        <span class="badge badge-info">
                            {{ $selectedCustomer->cdd_level->label() ?? 'Simplified' }} CDD
                        </span>
                    </div>
                </div>

                {{-- Sanction Warning --}}
                @if($selectedCustomer->sanction_hit || $selectedCustomer->pep_status)
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div>
                                <strong class="text-red-800">
                                    @if($selectedCustomer->sanction_hit)
                                        Sanction Match Detected
                                    @elseif($selectedCustomer->pep_status)
                                        Politically Exposed Person (PEP)
                                    @endif
                                </strong>
                                <p class="text-sm text-red-700 mt-1">
                                    @if($selectedCustomer->sanction_hit)
                                        This customer has a sanctions list match. Additional verification required.
                                    @elseif($selectedCustomer->pep_status)
                                        This customer is classified as a PEP. Enhanced due diligence applies.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Validation Error --}}
        @error('customerId')
            <p class="form-error mt-4">{{ $message }}</p>
        @enderror
    </div>
</div>
