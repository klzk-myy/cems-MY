@extends('layouts.base')

@section('title', 'New Transaction')

@section('header-title')
<div class="flex items-center gap-3">
    <a href="/transactions" class="btn btn-ghost btn-icon">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-semibold text-[--color-ink]">New Transaction</h1>
        <p class="text-sm text-[--color-ink-muted]">Create a new currency exchange transaction</p>
    </div>
</div>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <form method="POST" action="/transactions" class="space-y-6">
        @csrf

        {{-- Transaction Type --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transaction Type</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-4">
                    <label class="flex items-center gap-3 p-4 border border-[--color-border] rounded-xl cursor-pointer hover:bg-[--color-canvas-subtle] transition-colors has-[:checked]:border-[--color-accent] has-[:checked]:bg-[--color-accent]/5">
                        <input type="radio" name="type" value="Buy" class="form-checkbox" {{ old('type') === 'Buy' ? 'checked' : '' }} required>
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
                    <label class="flex items-center gap-3 p-4 border border-[--color-border] rounded-xl cursor-pointer hover:bg-[--color-canvas-subtle] transition-colors has-[:checked]:border-[--color-accent] has-[:checked]:bg-[--color-accent]/5">
                        <input type="radio" name="type" value="Sell" class="form-checkbox" {{ old('type') === 'Sell' ? 'checked' : '' }}>
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
            </div>
        </div>

        {{-- Customer Selection --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Customer</h3>
                <a href="/customers/create" class="btn btn-ghost btn-sm">Add New Customer</a>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Select Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Search or select customer...</option>
                        @foreach($customers ?? [] as $c)
                            <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->full_name }} ({{ $c->ic_number }})
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Currency & Amount --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Currency & Amount</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="form-label">Foreign Currency</label>
                        <select name="currency_code" class="form-select" required>
                            <option value="">Select currency...</option>
                            @foreach($currencies ?? [] as $code => $name)
                                <option value="{{ $code }}" {{ old('currency_code') === $code ? 'selected' : '' }}>
                                    {{ $code }} - {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        @error('currency_code')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Foreign Amount</label>
                        <input type="number" name="amount_foreign" class="form-input" step="0.01" min="0.01" value="{{ old('amount_foreign') }}" required placeholder="0.00">
                        @error('amount_foreign')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6 mt-6">
                    <div class="form-group">
                        <label class="form-label">Exchange Rate</label>
                        <input type="number" name="rate" class="form-input" step="0.0001" min="0.0001" value="{{ old('rate') ?? $suggested_rate ?? '' }}" required placeholder="0.0000">
                        <p class="form-hint">Current rate: {{ $suggested_rate ?? 'N/A' }}</p>
                        @error('rate')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">MYR Value (Calculated)</label>
                        <input type="text" class="form-input bg-[--color-canvas-subtle]" readonly placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>

        {{-- Counter Selection --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Counter & Branch</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Select branch...</option>
                            @foreach($branches ?? [] as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Counter</label>
                        <select name="counter_id" class="form-select" required>
                            <option value="">Select counter...</option>
                            @foreach($counters ?? [] as $counter)
                                <option value="{{ $counter->id }}" {{ old('counter_id') == $counter->id ? 'selected' : '' }}>
                                    {{ $counter->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('counter_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <a href="/transactions" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">
                Create Transaction
            </button>
        </div>
    </form>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountForeignInput = document.querySelector('input[name="amount_foreign"]');
    const rateInput = document.querySelector('input[name="rate"]');
    const myrValueInput = document.querySelector('input[readonly]');
    
    function calculateMyrValue() {
        const amountForeign = parseFloat(amountForeignInput.value) || 0;
        const rate = parseFloat(rateInput.value) || 0;
        const myrValue = amountForeign * rate;
        
        myrValueInput.value = myrValue.toFixed(2);
    }
    
    // Calculate on input change
    amountForeignInput.addEventListener('input', calculateMyrValue);
    rateInput.addEventListener('input', calculateMyrValue);
    
    // Initial calculation
    calculateMyrValue();
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const amountForeign = parseFloat(amountForeignInput.value) || 0;
        const rate = parseFloat(rateInput.value) || 0;
        
        if (amountForeign <= 0) {
            e.preventDefault();
            alert('Foreign amount must be greater than 0');
            return false;
        }
        
        if (rate <= 0) {
            e.preventDefault();
            alert('Exchange rate must be greater than 0');
            return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span> Creating...';
    });
});
</script>
@endsection
