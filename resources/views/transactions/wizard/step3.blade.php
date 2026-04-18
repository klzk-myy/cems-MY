{{-- Step 3: Review & Confirm (Hidden Template) --}}
<script id="step3-template" type="text/template">
<form id="step3-form" class="space-y-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Step 3: Review & Confirm</h2>
        <div id="review-cdd-badge" class="px-4 py-2 rounded-full text-sm font-medium"></div>
    </div>
    
    {{-- Transaction Summary Card --}}
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg p-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Transaction Summary
        </h3>
        
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="text-gray-600">Customer:</div>
            <div id="summary-customer" class="font-medium text-gray-900"></div>
            
            <div class="text-gray-600">Transaction Type:</div>
            <div id="summary-type" class="font-medium"></div>
            
            <div class="text-gray-600">Currency:</div>
            <div id="summary-currency" class="font-medium"></div>
            
            <div class="text-gray-600">Amount (Foreign):</div>
            <div id="summary-amount-foreign" class="font-medium"></div>
            
            <div class="text-gray-600">Exchange Rate:</div>
            <div id="summary-rate" class="font-medium"></div>
            
            <div class="text-gray-600">Amount (MYR):</div>
            <div id="summary-amount-local" class="font-bold text-xl text-blue-700"></div>
            
            <div class="text-gray-600">Purpose:</div>
            <div id="summary-purpose" class="font-medium"></div>
            
            <div class="text-gray-600">Source of Funds:</div>
            <div id="summary-source" class="font-medium"></div>
            
            <div class="text-gray-600">CDD Level:</div>
            <div id="summary-cdd-level" class="font-medium"></div>
        </div>
    </div>
    
    {{-- Risk Flags --}}
    <div id="summary-risk-flags" class="hidden bg-orange-50 border border-orange-200 rounded-lg p-4">
        <h4 class="font-bold text-orange-800 mb-2 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            Risk Alerts
        </h4>
        <ul id="risk-flags-list" class="list-disc list-inside text-orange-700 text-sm space-y-1"></ul>
    </div>
    
    {{-- Hold Warning --}}
    <div id="hold-warning" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-start">
            <svg class="h-5 w-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <h4 class="font-bold text-yellow-800">Manager Approval Required</h4>
                <p class="text-yellow-700 text-sm mt-1">
                    This transaction requires Enhanced Due Diligence and will be held pending manager approval.
                    Journal entries will be created only after approval.
                </p>
            </div>
        </div>
    </div>
    
    {{-- Confirmation Checkbox --}}
    <div class="border-t border-gray-200 pt-6">
        <div class="flex items-start space-x-3">
            <div class="flex items-center h-5">
                <input type="checkbox" id="confirm-details" required
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            </div>
            <div class="flex-1">
                <label for="confirm-details" class="font-medium text-gray-700">
                    I confirm that all transaction details are accurate
                </label>
                <p class="text-sm text-gray-500 mt-1">
                    I verify that the customer has been properly identified and all required documentation has been collected.
                    This transaction complies with BNM AML/CFT regulations.
                </p>
            </div>
        </div>
    </div>
    
    {{-- Navigation Buttons --}}
    <div class="flex justify-between pt-4 border-t border-gray-200">
        <button type="button" onclick="goBackToStep2()"
            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-8 rounded-lg transition-colors duration-200 flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back</span>
        </button>
        <button type="button" onclick="handleStep3Submit()"
            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg transition-colors duration-200 flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Confirm Transaction</span>
        </button>
    </div>
</form>

<script>
function populateStep3(summary) {
    // Set CDD badge
    const badge = document.getElementById('review-cdd-badge');
    const cddLevel = summary.cdd_level;
    
    badge.textContent = cddLevel.toUpperCase() + ' CDD';
    badge.className = 'px-4 py-2 rounded-full text-sm font-medium ' + 
        (cddLevel === 'simplified' ? 'bg-green-100 text-green-800' :
         cddLevel === 'standard' ? 'bg-amber-100 text-amber-800' :
         'bg-red-100 text-red-800');
    
    // Populate summary fields
    document.getElementById('summary-customer').textContent = summary.customer_name;
    document.getElementById('summary-type').textContent = summary.type === 'buy' ? 'Buy' : 'Sell';
    document.getElementById('summary-currency').textContent = summary.currency;
    document.getElementById('summary-amount-foreign').textContent = 
        parseFloat(summary.amount_foreign).toLocaleString('en-MY') + ' ' + summary.currency;
    document.getElementById('summary-rate').textContent = summary.rate;
    document.getElementById('summary-amount-local').textContent = 
        'RM ' + parseFloat(summary.amount_local).toLocaleString('en-MY', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    document.getElementById('summary-purpose').textContent = summary.purpose;
    document.getElementById('summary-source').textContent = summary.source_of_funds;
    document.getElementById('summary-cdd-level').textContent = cddLevel;
    
    // Show risk flags if any
    if (summary.risk_flags && summary.risk_flags.length > 0) {
        const flagsContainer = document.getElementById('summary-risk-flags');
        const flagsList = document.getElementById('risk-flags-list');
        flagsContainer.classList.remove('hidden');
        flagsList.innerHTML = summary.risk_flags.map(f => 
            `<li><strong>${f.type}:</strong> ${f.description}</li>`
        ).join('');
    }
    
    // Show hold warning if required
    if (summary.hold_required) {
        document.getElementById('hold-warning').classList.remove('hidden');
    }
}

function goBackToStep2() {
    // Reload step 2
    const cddLevel = document.getElementById('summary-cdd-level').textContent;
    loadStep2({cdd_level: cddLevel, required_documents: []});
}

async function handleStep3Submit() {
    const confirmCheckbox = document.getElementById('confirm-details');
    
    if (!confirmCheckbox.checked) {
        showAlert('error', 'Please confirm the transaction details');
        return;
    }
    
    await submitStep3();
}
</script>
</script>
