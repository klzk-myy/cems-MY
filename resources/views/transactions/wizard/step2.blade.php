{{-- Step 2: Customer Information (Hidden Template) --}}
<script id="step2-template" type="text/template">
<form id="step2-form" class="space-y-6" enctype="multipart/form-data">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Step 2: Customer Information</h2>
        <div id="cdd-badge" class="px-4 py-2 rounded-full text-sm font-medium"></div>
    </div>
    
    {{-- CDD Level Info --}}
    <div id="cdd-info" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h4 class="font-medium text-blue-900 mb-2">Required Documentation</h4>
        <ul id="required-docs-list" class="list-disc list-inside text-blue-800 space-y-1"></ul>
    </div>
    
    {{-- Customer Basic Info --}}
    <div class="bg-gray-50 rounded-lg p-6 space-y-6">
        <h3 class="text-lg font-semibold text-gray-800">Basic Information</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">
                    Occupation <span class="text-red-500">*</span>
                </label>
                <input type="text" name="customer[occupation]" required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="e.g., Engineer, Business Owner">
            </div>
            
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">
                    Employer Name
                </label>
                <input type="text" name="customer[employer_name]"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="Company or organization name">
            </div>
        </div>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">
                Employer Address
            </label>
            <textarea name="customer[employer_address]" rows="2"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Full employer address"></textarea>
        </div>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">
                Expected Annual Volume (MYR)
            </label>
            <input type="number" name="customer[annual_volume_estimate]" step="0.01" min="0"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="e.g., 50000">
        </div>
    </div>
    
    {{-- Standard CDD Documents --}}
    <div id="standard-docs" class="hidden bg-amber-50 rounded-lg p-6 space-y-6">
        <h3 class="text-lg font-semibold text-gray-800">Standard Due Diligence Documents</h3>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">
                Proof of Address <span class="text-red-500">*</span>
            </label>
            <input type="file" name="customer[proof_of_address]" accept=".pdf,.jpg,.png"
                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                data-required-for="standard">
            <p class="text-xs text-gray-500">Utility bill, bank statement, or government letter (PDF, JPG, PNG, max 5MB)</p>
        </div>
    </div>
    
    {{-- Enhanced CDD Documents --}}
    <div id="enhanced-docs" class="hidden bg-red-50 rounded-lg p-6 space-y-6">
        <h3 class="text-lg font-semibold text-gray-800">Enhanced Due Diligence Documents</h3>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">
                Passport Copy <span class="text-red-500">*</span>
            </label>
            <input type="file" name="customer[passport]" accept=".pdf,.jpg,.png"
                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                data-required-for="enhanced">
        </div>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">
                Beneficial Owner <span class="text-red-500">*</span>
            </label>
            <input type="text" name="customer[beneficial_owner]"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Name of ultimate beneficial owner">
        </div>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">
                Source of Wealth <span class="text-red-500">*</span>
            </label>
            <textarea name="customer[source_of_wealth]" rows="3"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Describe the source of customer's wealth (business, inheritance, investments, etc.)"></textarea>
        </div>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">
                Expected Transaction Frequency <span class="text-red-500">*</span>
            </label>
            <select name="transaction[expected_frequency]"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Frequency</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="annually">Annually</option>
            </select>
        </div>
    </div>
    
    {{-- Navigation Buttons --}}
    <div class="flex justify-between pt-4 border-t border-gray-200">
        <button type="button" onclick="goBackToStep1()"
            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-8 rounded-lg transition-colors duration-200 flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back</span>
        </button>
        <button type="button" onclick="handleStep2Submit()"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition-colors duration-200 flex items-center space-x-2">
            <span>Review Transaction</span>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
        </button>
    </div>
</form>

<script>
function populateStep2(data) {
    // Set CDD badge
    const badge = document.getElementById('cdd-badge');
    const cddLevel = data.cdd_level;
    
    badge.textContent = cddLevel.toUpperCase() + ' CDD';
    badge.className = 'px-4 py-2 rounded-full text-sm font-medium ' + 
        (cddLevel === 'simplified' ? 'bg-green-100 text-green-800' :
         cddLevel === 'standard' ? 'bg-amber-100 text-amber-800' :
         'bg-red-100 text-red-800');
    
    // Set required docs
    const docsList = document.getElementById('required-docs-list');
    docsList.innerHTML = '';
    
    data.required_documents.forEach(doc => {
        const li = document.createElement('li');
        li.innerHTML = `<span class="font-medium">${doc.label}</span> ${doc.required ? '<span class="text-red-500">*</span>' : ''}`;
        docsList.appendChild(li);
    });
    
    // Show/hide document sections
    if (cddLevel === 'standard' || cddLevel === 'enhanced') {
        document.getElementById('standard-docs').classList.remove('hidden');
    }
    
    if (cddLevel === 'enhanced') {
        document.getElementById('enhanced-docs').classList.remove('hidden');
    }
}

function goBackToStep1() {
    location.reload();
}

async function handleStep2Submit() {
    const form = document.getElementById('step2-form');
    const formData = new FormData(form);
    
    await submitStep2(formData);
}
</script>
</script>
