<div>
    <div class="card-header">
        <h3 class="card-title">Step 3: Review & Submit</h3>
        <p class="text-sm text-[--color-ink-muted]">Review transaction details before submitting</p>
    </div>
    <div class="card-body space-y-6">
        {{-- Customer Summary --}}
        <div class="p-4 bg-[--color-canvas-subtle] rounded-lg border border-[--color-border]">
            <h4 class="text-sm font-semibold text-[--color-ink-muted] uppercase tracking-wide mb-3">Customer</h4>
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-lg">{{ $customerName ?? 'N/A' }}</p>
                    @if($selectedCustomer)
                        <p class="text-sm text-[--color-ink-muted]">
                            {{ $selectedCustomer->ic_number ?? 'N/A' }}
                            @if($selectedCustomer->nationality)
                                &bull; {{ $selectedCustomer->nationality }}
                            @endif
                        </p>
                    @endif
                </div>
                <button type="button" wire:click="goToStep(1)" class="btn btn-ghost btn-sm">
                    Edit
                </button>
            </div>
        </div>

        {{-- Transaction Details --}}
        <div class="p-4 bg-[--color-canvas-subtle] rounded-lg border border-[--color-border]">
            <h4 class="text-sm font-semibold text-[--color-ink-muted] uppercase tracking-wide mb-3">Transaction Details</h4>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Transaction Type</p>
                    <p class="font-semibold">
                        @if($type === 'Buy')
                            <span class="text-[--color-success]">Buy Foreign Currency</span>
                        @else
                            <span class="text-[--color-warning]">Sell Foreign Currency</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Currency</p>
                    <p class="font-semibold">{{ $currencyCode ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Foreign Amount</p>
                    <p class="font-semibold">{{ number_format((float)($amountForeign ?? 0), 2) }} {{ $currencyCode ?? '' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Exchange Rate</p>
                    <p class="font-semibold">{{ number_format((float)($rate ?? 0), 4) }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">MYR Value</p>
                    <p class="font-semibold text-lg">RM {{ number_format((float)($amountLocal ?? 0), 2) }}</p>
                </div>
            </div>
        </div>

        {{-- Branch & Counter --}}
        <div class="p-4 bg-[--color-canvas-subtle] rounded-lg border border-[--color-border]">
            <h4 class="text-sm font-semibold text-[--color-ink-muted] uppercase tracking-wide mb-3">Branch & Counter</h4>
            <div class="flex items-center justify-between">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Branch</p>
                        <p class="font-semibold">
                            @foreach($branches as $branch)
                                @if($branch->id === $branchId)
                                    {{ $branch->name }}
                                @endif
                            @endforeach
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Counter</p>
                        <p class="font-semibold">
                            @foreach($counters ?? [] as $counter)
                                @if($counter->id === $counterId)
                                    {{ $counter->name }}
                                @endif
                            @endforeach
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="goToStep(2)" class="btn btn-ghost btn-sm">
                    Edit
                </button>
            </div>
        </div>

        {{-- Purpose & Source of Funds --}}
        <div class="p-4 bg-[--color-canvas-subtle] rounded-lg border border-[--color-border]">
            <h4 class="text-sm font-semibold text-[--color-ink-muted] uppercase tracking-wide mb-3">Purpose & Source of Funds</h4>
            <div class="flex items-center justify-between">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Purpose</p>
                        <p class="font-semibold capitalize">{{ $purpose ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">Source of Funds</p>
                        <p class="font-semibold capitalize">{{ str_replace('_', ' ', $sourceOfFunds ?? 'N/A') }}</p>
                    </div>
                </div>
                <button type="button" wire:click="goToStep(2)" class="btn btn-ghost btn-sm">
                    Edit
                </button>
            </div>
        </div>

        {{-- Compliance Notice --}}
        @if($selectedCustomer && ($selectedCustomer->sanction_hit || $selectedCustomer->pep_status || ($selectedCustomer->risk_rating === 'High')))
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <strong class="text-yellow-800">Compliance Alert</strong>
                        <p class="text-sm text-yellow-700 mt-1">
                            This transaction requires additional compliance review based on customer risk factors.
                            @if($selectedCustomer->sanction_hit)
                                <br>Sanction match detected - manual verification required.
                            @endif
                            @if($selectedCustomer->pep_status)
                                <br>PEP customer - Enhanced Due Diligence applies.
                            @endif
                            @if($selectedCustomer->risk_rating === 'High')
                                <br>High risk customer - transaction will require manager approval.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Submit Section --}}
        <div class="pt-4 border-t border-[--color-border]">
            <div class="flex items-center justify-between">
                <div class="text-sm text-[--color-ink-muted]">
                    By submitting this transaction, you confirm that all information is accurate and complete.
                </div>
                <button type="button" wire:click="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">
                        Submit Transaction
                    </span>
                    <span wire:loading wire:target="submit">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
