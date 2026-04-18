{{-- Step 1: Transaction Details --}}
<form id="step1-form" class="space-y-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Step 1: Transaction Details</h2>
        <span class="text-sm text-gray-500">All fields marked with * are required</span>
    </div>
    
    {{-- Customer Selection --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-2">
            <label for="customer_id" class="block text-sm font-medium text-gray-700">
                Customer <span class="text-red-500">*</span>
            </label>
            <select name="customer_id" id="customer_id" required 
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-colors">
                <option value="">Select Customer</option>
                @foreach($customers ?? [] as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->full_name }} ({{ $customer->id_number }})</option>
                @endforeach
            </select>
            @error('customer_id')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        
        <div class="space-y-2">
            <label for="type" class="block text-sm font-medium text-gray-700">
                Transaction Type <span class="text-red-500">*</span>
            </label>
            <select name="type" id="type" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Type</option>
                <option value="buy">Buy (Customer sells foreign currency)</option>
                <option value="sell">Sell (Customer buys foreign currency)</option>
            </select>
        </div>
    </div>
    
    {{-- Currency and Amount --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="space-y-2">
            <label for="currency_code" class="block text-sm font-medium text-gray-700">
                Currency <span class="text-red-500">*</span>
            </label>
            <select name="currency_code" id="currency_code" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                onchange="updateRate()">
                <option value="">Select Currency</option>
                @foreach($currencies ?? [] as $currency)
                    <option value="{{ $currency->code }}" data-rate="{{ $currency->rate ?? 4.50 }}">
                        {{ $currency->code }} - {{ $currency->name }}
                    </option>
                @endforeach
            </select>
        </div>
        
        <div class="space-y-2">
            <label for="amount_foreign" class="block text-sm font-medium text-gray-700">
                Amount (Foreign) <span class="text-red-500">*</span>
            </label>
            <input type="number" name="amount_foreign" id="amount_foreign" step="0.01" min="0.01" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                onchange="calculateLocalAmount()">
        </div>
        
        <div class="space-y-2">
            <label for="rate" class="block text-sm font-medium text-gray-700">
                Exchange Rate <span class="text-red-500">*</span>
            </label>
            <input type="number" name="rate" id="rate" step="0.0001" min="0.0001" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                onchange="calculateLocalAmount()">
        </div>
    </div>
    
    {{-- Calculated Amount --}}
    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-6 border border-blue-200">
        <label class="block text-sm font-medium text-blue-900 mb-1">Amount (MYR)</label>
        <div id="amount_local_display" class="text-3xl font-bold text-blue-700">RM 0.00</div>
        <input type="hidden" name="amount_local" id="amount_local">
    </div>
    
    {{-- Purpose and Source --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-2">
            <label for="purpose" class="block text-sm font-medium text-gray-700">
                Purpose <span class="text-red-500">*</span>
            </label>
            <select name="purpose" id="purpose" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Purpose</option>
                <option value="Travel">Travel</option>
                <option value="Education">Education</option>
                <option value="Medical">Medical</option>
                <option value="Business">Business</option>
                <option value="Investment">Investment</option>
                <option value="Family Support">Family Support</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="space-y-2">
            <label for="source_of_funds" class="block text-sm font-medium text-gray-700">
                Source of Funds <span class="text-red-500">*</span>
            </label>
            <select name="source_of_funds" id="source_of_funds" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Source</option>
                <option value="Salary">Salary</option>
                <option value="Business Income">Business Income</option>
                <option value="Savings">Savings</option>
                <option value="Investment">Investment</option>
                <option value="Loan">Loan</option>
                <option value="Gift">Gift</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>
    
    {{-- Till Selection --}}
    <div class="space-y-2">
        <label for="till_id" class="block text-sm font-medium text-gray-700">
            Till <span class="text-red-500">*</span>
        </label>
        <select name="till_id" id="till_id" required
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Select Till</option>
            @foreach($tills ?? [] as $till)
                <option value="{{ $till->id }}">{{ $till->name }} ({{ $till->branch->name ?? 'Main' }})</option>
            @endforeach
        </select>
    </div>
    
    {{-- Teller Override --}}
    <div class="border border-amber-200 rounded-lg p-4 bg-amber-50">
        <div class="flex items-start space-x-3">
            <div class="flex items-center h-5">
                <input type="checkbox" name="collect_additional_details" id="collect_additional_details" value="1"
                    class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded">
            </div>
            <div class="flex-1">
                <label for="collect_additional_details" class="font-medium text-amber-900">
                    Teller Override: Collect Enhanced Due Diligence
                </label>
                <p class="text-sm text-amber-700 mt-1">
                    Check this box if you suspect unusual activity and want to collect additional customer information
                    even if the amount is below the threshold. This will upgrade the CDD level to Standard or Enhanced.
                </p>
            </div>
        </div>
    </div>
    
    {{-- Submit Button --}}
    <div class="flex justify-end pt-4 border-t border-gray-200">
        <button type="button" onclick="handleStep1Submit()" 
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition-colors duration-200 flex items-center space-x-2">
            <span>Continue to Customer Details</span>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
        </button>
    </div>
</form>

<script>
function calculateLocalAmount() {
    const amount = parseFloat(document.getElementById('amount_foreign').value) || 0;
    const rate = parseFloat(document.getElementById('rate').value) || 0;
    const localAmount = (amount * rate).toFixed(2);
    
    document.getElementById('amount_local').value = localAmount;
    document.getElementById('amount_local_display').textContent = 'RM ' + parseFloat(localAmount).toLocaleString('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function updateRate() {
    const currency = document.getElementById('currency_code');
    const rateInput = document.getElementById('rate');
    const selectedOption = currency.options[currency.selectedIndex];
    
    if (selectedOption && selectedOption.dataset.rate) {
        rateInput.value = selectedOption.dataset.rate;
        calculateLocalAmount();
    }
}

async function handleStep1Submit() {
    const form = document.getElementById('step1-form');
    
    // Basic validation
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = {
        customer_id: parseInt(form.customer_id.value),
        type: form.type.value,
        currency_code: form.currency_code.value,
        amount_foreign: form.amount_foreign.value,
        rate: form.rate.value,
        till_id: form.till_id.value,
        purpose: form.purpose.value,
        source_of_funds: form.source_of_funds.value,
        collect_additional_details: form.collect_additional_details?.checked || false
    };
    
    await submitStep1(formData);
}
</script>
