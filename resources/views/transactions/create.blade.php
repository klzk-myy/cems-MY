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
    <form method="POST" action="/transactions" class="space-y-6" id="transaction-form">
        @csrf

        {{-- Transaction Type --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transaction Type</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-4">
                    <label class="flex items-center gap-3 p-4 border border-[--color-border] rounded-xl cursor-pointer hover:bg-[--color-canvas-subtle] transition-colors has-[:checked]:border-[--color-accent] has-[:checked]:bg-[--color-accent]/5">
                        <input type="radio" name="type" value="Buy" class="form-checkbox" checked required>
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
                        <input type="radio" name="type" value="Sell" class="form-checkbox">
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
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Search Customer (Name or IC Number)</label>
                    <div class="relative">
                        <input type="text"
                               id="customer-search"
                               class="form-input"
                               placeholder="Type customer name or IC number to search..."
                               autocomplete="off">
                        <input type="hidden" name="customer_id" id="customer_id" value="">
                        <div id="customer-search-results" class="absolute z-50 w-full mt-1 bg-white border border-[--color-border] rounded-lg shadow-lg hidden max-h-80 overflow-y-auto">
                        </div>
                    </div>
                    @error('customer_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Selected Customer Info --}}
                <div id="selected-customer-info" class="mt-4 p-4 bg-[--color-canvas-subtle] rounded-lg hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-lg" id="selected-name"></p>
                            <p class="text-sm text-[--color-ink-muted]" id="selected-ic"></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="risk-badge" class="badge"></span>
                            <span id="cdd-badge" class="badge badge-info"></span>
                        </div>
                    </div>
                    <div id="sanction-warning" class="mt-3 p-3 bg-red-100 border border-red-300 rounded-lg text-red-800 hidden">
                        <div class="flex items-start gap-2">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <strong>SANCTION MATCH DETECTED</strong>
                                <p class="text-sm mt-1" id="sanction-details"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- No Customer Found - Quick Create --}}
                <div id="no-customer-found" class="mt-4 p-4 border-2 border-dashed border-[--color-border] rounded-lg text-center hidden">
                    <p class="text-[--color-ink-muted] mb-3">No existing customer found. Create new customer:</p>
                    <button type="button" id="btn-create-customer" class="btn btn-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create New Customer
                    </button>
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
                        <select name="currency_code" id="currency-select" class="form-select" required>
                            <option value="">Select currency...</option>
                            @foreach($currencies ?? [] as $code => $name)
                                <option value="{{ $code }}">{{ $code }} - {{ $name }}</option>
                            @endforeach
                        </select>
                        @error('currency_code')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Foreign Amount</label>
                        <input type="number" name="amount_foreign" id="amount-foreign" class="form-input" step="0.01" min="0.01" placeholder="0.00" required>
                        @error('amount_foreign')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6 mt-6">
                    <div class="form-group">
                        <label class="form-label">
                            Exchange Rate
                            <span class="text-xs text-[--color-ink-muted] ml-1">(Daily Rate)</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="rate" id="exchange-rate" class="form-input" step="0.0001" min="0.0001" placeholder="0.0000" required>
                            <button type="button" id="btn-reset-rate" class="btn btn-ghost btn-sm text-xs" title="Reset to daily rate">Reset</button>
                        </div>
                        <p class="form-hint" id="rate-hint">Select currency to see daily rate</p>
                        @error('rate')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">MYR Value (Calculated)</label>
                        <input type="text" id="myr-value" class="form-input bg-[--color-canvas-subtle]" readonly placeholder="0.00">
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
                        <select name="branch_id" id="branch-select" class="form-select" required>
                            <option value="">Select branch...</option>
                            @foreach($branches ?? [] as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Counter</label>
                        <select name="counter_id" id="counter-select" class="form-select" required>
                            <option value="">Select counter...</option>
                            @foreach($counters ?? [] as $counter)
                                <option value="{{ $counter->id }}">{{ $counter->name }}</option>
                            @endforeach
                        </select>
                        @error('counter_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Transaction Details --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transaction Details</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="form-label">Purpose</label>
                        <select name="purpose" class="form-select" required>
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
                        <select name="source_of_funds" class="form-select" required>
                            <option value="">Select source...</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="salary">Salary</option>
                            <option value="savings">Savings</option>
                            <option value="business_income">Business Income</option>
                            <option value="other">Other</option>
                        </select>
                        @error('source_of_funds')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden fields --}}
        <input type="hidden" name="till_id" value="TILL-{{ \Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <a href="/transactions" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary" id="btn-submit">
                Create Transaction
            </button>
        </div>
    </form>
</div>

{{-- New Customer Modal --}}
<div id="new-customer-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" id="modal-backdrop"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-[--color-border]">
                <h3 class="text-lg font-semibold">Create New Customer</h3>
                <p class="text-sm text-[--color-ink-muted]">Customer not found in database. Please fill in details.</p>
            </div>
            <form id="quick-customer-form" class="p-6 space-y-4">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="new-customer-name" class="form-input" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">ID Type *</label>
                        <select name="id_type" id="new-customer-id-type" class="form-select" required>
                            <option value="MyKad">MyKad</option>
                            <option value="Passport">Passport</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ID Number *</label>
                        <input type="text" name="id_number" id="new-customer-id-number" class="form-input" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Date of Birth *</label>
                        <input type="date" name="date_of_birth" id="new-customer-dob" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nationality *</label>
                        <input type="text" name="nationality" id="new-customer-nationality" class="form-input" required placeholder="e.g., Malaysian">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="new-customer-phone" class="form-input" placeholder="e.g., 0123456789">
                </div>
                <div id="new-customer-error" class="p-3 bg-red-100 border border-red-300 rounded-lg text-red-700 hidden"></div>
            </form>
            <div class="p-6 border-t border-[--color-border] flex justify-end gap-3">
                <button type="button" id="btn-cancel-customer" class="btn btn-ghost">Cancel</button>
                <button type="button" id="btn-save-customer" class="btn btn-primary">
                    Create & Select Customer
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Global state
let exchangeRates = {};
let dailyRates = {};
let selectedCustomer = null;

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('transaction-form');
    const customerSearch = document.getElementById('customer-search');
    const searchResults = document.getElementById('customer-search-results');
    const customerIdInput = document.getElementById('customer_id');
    const selectedCustomerInfo = document.getElementById('selected-customer-info');
    const noCustomerFound = document.getElementById('no-customer-found');
    const currencySelect = document.getElementById('currency-select');
    const exchangeRateInput = document.getElementById('exchange-rate');
    const rateHint = document.getElementById('rate-hint');
    const amountForeignInput = document.getElementById('amount-foreign');
    const myrValueInput = document.getElementById('myr-value');
    const btnResetRate = document.getElementById('btn-reset-rate');
    const newCustomerModal = document.getElementById('new-customer-modal');
    const quickCustomerForm = document.getElementById('quick-customer-form');

    // Load exchange rates on page load
    loadExchangeRates().then(() => {
        console.log('Exchange rates loaded:', exchangeRates);
    }).catch(err => {
        console.error('Failed to load exchange rates:', err);
    });

    // Customer Search
    let searchTimeout = null;
    customerSearch.addEventListener('input', function() {
        const query = this.value.trim();

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            noCustomerFound.classList.add('hidden');
            return;
        }

        searchTimeout = setTimeout(async function() {
            try {
                const response = await fetch(`/customers/search?query=${encodeURIComponent(query)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success && data.results.length > 0) {
                    // Customers found - show dropdown
                    let html = `<div class="p-3 text-sm text-[--color-ink-muted] border-b border-[--color-border]">Found ${data.results.length} customer(s):</div>`;
                    html += data.results.map(customer => {
                        const riskClass = customer.risk_rating === 'High' ? 'bg-red-100 text-red-800' :
                                         customer.risk_rating === 'Medium' ? 'bg-yellow-100 text-yellow-800' :
                                         'bg-green-100 text-green-800';
                        const sanctionIcon = customer.sanction_warning || customer.is_sanctioned ?
                            ' <span class="text-red-600 font-bold" title="Sanction Warning">⚠️</span>' : '';
                        const pepIcon = customer.is_pep ? ' <span class="text-orange-500" title="PEP">PEP</span>' : '';

                        return `
                            <div class="p-3 hover:bg-[--color-canvas-subtle] cursor-pointer border-b border-[--color-border] last:border-b-0 customer-result"
                                 data-id="${customer.id}"
                                 data-name="${customer.full_name}"
                                 data-ic="${customer.ic_number_masked || ''}"
                                 data-risk="${customer.risk_rating}"
                                 data-cdd="${customer.cdd_level}"
                                 data-sanction-warning="${customer.sanction_warning}"
                                 data-sanction-action="${customer.sanction_action}"
                                 data-sanction-matches='${JSON.stringify(customer.sanction_matches || [])}'>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">${customer.full_name}</span>
                                        ${sanctionIcon}${pepIcon}
                                    </div>
                                    <span class="text-xs ${riskClass} px-2 py-1 rounded">${customer.risk_rating}</span>
                                </div>
                                <div class="text-xs text-[--color-ink-muted] mt-1">
                                    ${customer.ic_number_masked || ''} ${customer.nationality ? '• ' + customer.nationality : ''}
                                </div>
                            </div>
                        `;
                    }).join('');
                    searchResults.innerHTML = html;
                    searchResults.classList.remove('hidden');
                    noCustomerFound.classList.add('hidden');
                } else {
                    // No customers found - show create button
                    searchResults.classList.add('hidden');
                    noCustomerFound.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Customer search error:', error);
            }
        }, 300);
    });

    // Select customer from results
    searchResults.addEventListener('click', function(e) {
        const result = e.target.closest('.customer-result');
        if (!result) return;

        selectCustomer({
            id: result.dataset.id,
            full_name: result.dataset.name,
            ic_number_masked: result.dataset.ic,
            risk_rating: result.dataset.risk,
            cdd_level: result.dataset.cdd,
            sanction_warning: result.dataset.sanctionWarning === 'true',
            sanction_action: result.dataset.sanctionAction,
            sanction_matches: JSON.parse(result.dataset.sanctionMatches || '[]')
        });

        searchResults.classList.add('hidden');
        customerSearch.value = result.dataset.name;
    });

    // Select customer function
    function selectCustomer(customer) {
        selectedCustomer = customer;
        customerIdInput.value = customer.id;

        document.getElementById('selected-name').textContent = customer.full_name;
        document.getElementById('selected-ic').textContent = customer.ic_number_masked || '';

        // Risk badge
        const riskBadge = document.getElementById('risk-badge');
        riskBadge.className = 'badge';
        if (customer.risk_rating === 'High') {
            riskBadge.classList.add('bg-red-100', 'text-red-800');
        } else if (customer.risk_rating === 'Medium') {
            riskBadge.classList.add('bg-yellow-100', 'text-yellow-800');
        } else {
            riskBadge.classList.add('bg-green-100', 'text-green-800');
        }
        riskBadge.textContent = customer.risk_rating;

        // CDD badge
        document.getElementById('cdd-badge').textContent = customer.cdd_level;

        // Sanction warning
        const sanctionWarning = document.getElementById('sanction-warning');
        if (customer.sanction_warning || customer.sanction_action === 'flag' || customer.sanction_action === 'block') {
            sanctionWarning.classList.remove('hidden');
            const details = customer.sanction_matches.length > 0
                ? customer.sanction_matches.map(m => `${m.entity_name} (${m.list}, ${m.score}% match)`).join(', ')
                : 'Potential match found - please verify customer identity';
            document.getElementById('sanction-details').textContent = details;
        } else {
            sanctionWarning.classList.add('hidden');
        }

        selectedCustomerInfo.classList.remove('hidden');
        noCustomerFound.classList.add('hidden');
    }

    // Load exchange rates
    async function loadExchangeRates() {
        try {
            console.log('Loading exchange rates from /customers/exchange-rates');
            const response = await fetch('/customers/exchange-rates', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            const data = await response.json();
            console.log('Exchange rates response:', data);
            if (data.success) {
                exchangeRates = data.rates;
                dailyRates = JSON.parse(JSON.stringify(data.rates)); // Clone
                console.log('Rates stored in exchangeRates:', exchangeRates);
            } else {
                console.warn('Unexpected response format:', data);
            }
        } catch (error) {
            console.error('Failed to load exchange rates:', error);
            throw error; // Re-throw for Promise.catch
        }
    }

    // Currency selection - auto-fill rate
    currencySelect.addEventListener('change', function() {
        const currency = this.value;
        const transactionType = document.querySelector('input[name="type"]:checked').value;

        if (currency && exchangeRates[currency]) {
            // Use daily rate based on transaction type
            const rateKey = transactionType === 'Sell' ? 'sell' : 'buy';
            const rate = exchangeRates[currency][rateKey];

            exchangeRateInput.value = rate;
            dailyRates[currency] = { [rateKey]: rate }; // Store daily rate
            rateHint.textContent = `Daily ${rateKey} rate: ${rate}`;

            calculateMyrValue();
        }
    });

    // Transaction type change - update rate
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (currencySelect.value) {
                currencySelect.dispatchEvent(new Event('change'));
            }
        });
    });

    // Reset rate to daily
    btnResetRate.addEventListener('click', function() {
        const currency = currencySelect.value;
        const transactionType = document.querySelector('input[name="type"]:checked').value;

        if (currency && dailyRates[currency]) {
            const rateKey = transactionType === 'Sell' ? 'sell' : 'buy';
            exchangeRateInput.value = dailyRates[currency][rateKey];
            rateHint.textContent = `Daily ${rateKey} rate: ${dailyRates[currency][rateKey]}`;
            calculateMyrValue();
        }
    });

    // Calculate MYR value
    function calculateMyrValue() {
        const amount = parseFloat(amountForeignInput.value) || 0;
        const rate = parseFloat(exchangeRateInput.value) || 0;
        myrValueInput.value = (amount * rate).toFixed(2);
    }

    amountForeignInput.addEventListener('input', calculateMyrValue);
    exchangeRateInput.addEventListener('input', calculateMyrValue);

    // New Customer Modal
    document.getElementById('btn-create-customer').addEventListener('click', function() {
        // Pre-fill search query as customer name suggestion
        const query = customerSearch.value.trim();
        if (query) {
            document.getElementById('new-customer-name').value = query;
        }
        newCustomerModal.classList.remove('hidden');
    });

    document.getElementById('btn-cancel-customer').addEventListener('click', function() {
        newCustomerModal.classList.add('hidden');
    });

    document.getElementById('modal-backdrop').addEventListener('click', function() {
        newCustomerModal.classList.add('hidden');
    });

    // Save new customer
    document.getElementById('btn-save-customer').addEventListener('click', async function() {
        const formData = new FormData(quickCustomerForm);
        const data = Object.fromEntries(formData);

        // Validate required fields
        if (!data.full_name || !data.id_type || !data.id_number || !data.date_of_birth || !data.nationality) {
            document.getElementById('new-customer-error').textContent = 'Please fill in all required fields';
            document.getElementById('new-customer-error').classList.remove('hidden');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span> Creating...';

        try {
            const response = await fetch('/customers/quick-create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                // Update exchange rates if provided
                if (result.exchange_rates) {
                    exchangeRates = result.exchange_rates;
                    dailyRates = JSON.parse(JSON.stringify(result.exchange_rates));
                }

                // Select the new customer
                selectCustomer(result.customer);

                // Update search field
                customerSearch.value = result.customer.full_name;

                // Close modal
                newCustomerModal.classList.add('hidden');
                quickCustomerForm.reset();

                // Update currency dropdown with new rates
                if (result.exchange_rates) {
                    Object.keys(result.exchange_rates).forEach(code => {
                        const option = currencySelect.querySelector(`option[value="${code}"]`);
                        if (option) {
                            exchangeRates[code] = result.exchange_rates[code];
                        }
                    });
                }
            } else {
                document.getElementById('new-customer-error').textContent = result.message || 'Failed to create customer';
                document.getElementById('new-customer-error').classList.remove('hidden');
            }
        } catch (error) {
            document.getElementById('new-customer-error').textContent = 'Error: ' + error.message;
            document.getElementById('new-customer-error').classList.remove('hidden');
        }

        btn.disabled = false;
        btn.innerHTML = 'Create & Select Customer';
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        if (!customerIdInput.value) {
            e.preventDefault();
            alert('Please select or create a customer first');
            return false;
        }

        const amount = parseFloat(amountForeignInput.value) || 0;
        const rate = parseFloat(exchangeRateInput.value) || 0;

        if (amount <= 0) {
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
        const submitBtn = document.getElementById('btn-submit');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span> Creating...';
    });

    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (!customerSearch.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });
});
</script>
@endsection
