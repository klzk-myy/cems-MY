<div>
    <div class="card-header">
        <h3 class="card-title">Step 2: Currency & Amount</h3>
        <p class="text-sm text-[--color-ink-muted]">Enter transaction details and exchange rate</p>
    </div>
    <div class="card-body space-y-6">
        {{-- Transaction Type --}}
        <div class="form-group">
            <label class="form-label">Transaction Type</label>
            <div class="grid grid-cols-2 gap-4">
                <label class="flex items-center gap-3 p-4 border border-[--color-border] rounded-xl cursor-pointer hover:bg-[--color-canvas-subtle] transition-colors has-[:checked]:border-[--color-accent] has-[:checked]:bg-[--color-accent]/5
                    {{ $type === 'Buy' ? 'border-[--color-accent] bg-[--color-accent]/5' : '' }}">
                    <input type="radio" wire:model.live="type" value="Buy" class="form-checkbox" required>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="font-semibold">Buy Foreign Currency</span>
                        </div>
                        <p class="text-sm text-[--color-ink-muted] mt-1">Customer sells foreign currency to us</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-4 border border-[--color-border] rounded-xl cursor-pointer hover:bg-[--color-canvas-subtle] transition-colors has-[:checked]:border-[--color-accent] has-[:checked]:bg-[--color-accent]/5
                    {{ $type === 'Sell' ? 'border-[--color-accent] bg-[--color-accent]/5' : '' }}">
                    <input type="radio" wire:model.live="type" value="Sell" class="form-checkbox">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-[--color-warning]/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="font-semibold">Sell Foreign Currency</span>
                        </div>
                        <p class="text-sm text-[--color-ink-muted] mt-1">Customer buys foreign currency from us</p>
                    </div>
                </label>
            </div>
            @error('type')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Currency & Amount --}}
        <div class="grid grid-cols-2 gap-6">
            <div class="form-group">
                <label class="form-label">Foreign Currency</label>
                <select wire:model.live="currencyCode" class="form-select" required>
                    <option value="">Select currency...</option>
                    @foreach($currencies as $currency)
                        <option value="{{ $currency->code }}">{{ $currency->code }} - {{ $currency->name }}</option>
                    @endforeach
                </select>
                @error('currencyCode')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Foreign Amount</label>
                <input type="number" step="0.01" min="0.01" wire:model.live="amountForeign" class="form-input" placeholder="0.00" required>
                @error('amountForeign')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Exchange Rate --}}
        <div class="grid grid-cols-2 gap-6">
            <div class="form-group">
                <label class="form-label">
                    Exchange Rate
                    <span class="text-xs text-[--color-ink-muted] ml-1">(Daily Rate)</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="number" step="0.0001" min="0.0001" wire:model.live="rate" class="form-input" placeholder="0.0000" required>
                    @if($currencyCode && isset($exchangeRates[$currencyCode]))
                        <button type="button" wire:click="resetRateToDaily" class="btn btn-ghost btn-sm text-xs" title="Reset to daily rate">
                            Reset
                        </button>
                    @endif
                </div>
                @if($currencyCode && isset($exchangeRates[$currencyCode]))
                    <p class="form-hint">
                        Daily rate: {{ $type === 'Sell' ? $exchangeRates[$currencyCode]['sell'] : $exchangeRates[$currencyCode]['buy'] }}
                    </p>
                @endif
                @error('rate')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label">MYR Value (Calculated)</label>
                <input type="text" value="{{ $amountLocal ? number_format((float)$amountLocal, 2) : '0.00' }}" class="form-input bg-[--color-canvas-subtle]" readonly>
            </div>
        </div>

        {{-- Branch & Counter --}}
        <div class="grid grid-cols-2 gap-6">
            <div class="form-group">
                <label class="form-label">Branch</label>
                <select wire:model.live="branchId" class="form-select" required>
                    <option value="">Select branch...</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branchId')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Counter</label>
                <select wire:model.live="counterId" class="form-select" required>
                    <option value="">Select counter...</option>
                    @foreach($counters ?? [] as $counter)
                        <option value="{{ $counter->id }}">{{ $counter->name }}</option>
                    @endforeach
                </select>
                @error('counterId')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Purpose & Source of Funds --}}
        <div class="grid grid-cols-2 gap-6">
            <div class="form-group">
                <label class="form-label">Purpose</label>
                <select wire:model.live="purpose" class="form-select" required>
                    <option value="">Select purpose...</option>
                    <option value="travel">Travel</option>
                    <option value="education">Education</option>
                    <option value="business">Business</option>
                    <option value="medical">Medical</option>
                    <option value="remittance">Remittance</option>
                    <option value="investment">Investment</option>
                    <option value="other">Other</option>
                </select>
                @error('purpose')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Source of Funds</label>
                <select wire:model.live="sourceOfFunds" class="form-select" required>
                    <option value="">Select source...</option>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="salary">Salary</option>
                    <option value="savings">Savings</option>
                    <option value="business_income">Business Income</option>
                    <option value="other">Other</option>
                </select>
                @error('sourceOfFunds')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</div>
