@extends('layouts.base')

@section('title', 'New Transaction - Wizard')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Create New Transaction</h1>
        
        {{-- Progress Bar --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center w-full">
                    <div id="step1-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-bold transition-colors duration-300">1</div>
                    <div id="step1-line" class="flex-1 h-1 bg-blue-600 mx-2 transition-colors duration-300"></div>
                    <div id="step2-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-300 text-gray-600 font-bold transition-colors duration-300">2</div>
                    <div id="step2-line" class="flex-1 h-1 bg-gray-300 mx-2 transition-colors duration-300"></div>
                    <div id="step3-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-300 text-gray-600 font-bold transition-colors duration-300">3</div>
                </div>
            </div>
            <div class="flex justify-between mt-2 text-sm text-gray-600">
                <span class="flex-1 text-center font-medium">Transaction Details</span>
                <span class="flex-1 text-center">Customer Information</span>
                <span class="flex-1 text-center">Review & Confirm</span>
            </div>
        </div>
        
        {{-- Alert Container --}}
        <div id="alert-container" class="mb-4"></div>
        
        {{-- Wizard Container --}}
        <div class="bg-white rounded-lg shadow-lg p-6">
            @include('transactions.wizard.step1')
        </div>
    </div>
</div>

@push('scripts')
<script>
// Wizard state
let wizardSessionId = null;
let currentStep = 1;

// Step 1: Submit transaction details
async function submitStep1(formData) {
    showLoading('Processing transaction details...');
    
    try {
        const response = await fetch('/api/wizard/transactions/step1', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.status === 'blocked') {
            showAlert('error', data.message);
            return false;
        }
        
        if (data.status === 'success') {
            wizardSessionId = data.wizard_session_id;
            
            if (data.risk_flags && data.risk_flags.length > 0) {
                showRiskFlags(data.risk_flags);
            }
            
            if (data.hold_required) {
                showAlert('warning', 'This transaction requires manager approval (Enhanced CDD). Journal entries will be created after approval.');
            }
            
            loadStep2(data);
            return true;
        }
    } catch (error) {
        hideLoading();
        showAlert('error', 'Network error. Please try again.');
        return false;
    }
}

// Step 2: Submit customer details
async function submitStep2(formData) {
    showLoading('Processing customer information...');
    
    try {
        formData.append('wizard_session_id', wizardSessionId);
        
        const response = await fetch('/api/wizard/transactions/step2', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.status === 'success') {
            loadStep3(data.transaction_summary);
            return true;
        }
    } catch (error) {
        hideLoading();
        showAlert('error', 'Network error. Please try again.');
        return false;
    }
}

// Step 3: Confirm transaction
async function submitStep3() {
    showLoading('Creating transaction...');
    
    try {
        const response = await fetch('/api/wizard/transactions/step3', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                wizard_session_id: wizardSessionId,
                confirm_details: true,
                idempotency_key: generateIdempotencyKey()
            })
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.status === 'success') {
            window.location.href = `/transactions/${data.transaction_id}`;
        } else {
            showAlert('error', data.message || 'Transaction creation failed');
        }
    } catch (error) {
        hideLoading();
        showAlert('error', 'Failed to create transaction. Please try again.');
    }
}

// UI Helpers
function showAlert(type, message) {
    const container = document.getElementById('alert-container');
    const colors = {
        error: 'bg-red-100 border-red-400 text-red-700',
        warning: 'bg-yellow-100 border-yellow-400 text-yellow-700',
        success: 'bg-green-100 border-green-400 text-green-700'
    };
    
    container.innerHTML = `
        <div class="${colors[type]} px-4 py-3 rounded border" role="alert">
            <span class="block sm:inline">${message}</span>
        </div>
    `;
}

function showRiskFlags(flags) {
    let html = '<div class="bg-orange-50 border border-orange-200 rounded p-4 mb-4">';
    html += '<h4 class="font-bold text-orange-800 mb-2 flex items-center">';
    html += '<svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
    html += 'Risk Alerts Detected</h4><ul class="list-disc list-inside text-orange-700">';
    
    flags.forEach(flag => {
        html += `<li><strong>${flag.type}:</strong> ${flag.description}</li>`;
    });
    
    html += '</ul></div>';
    document.getElementById('alert-container').innerHTML = html;
}

function showLoading(message) {
    document.getElementById('alert-container').innerHTML = `
        <div class="bg-blue-50 border border-blue-200 rounded p-4">
            <div class="flex items-center">
                <svg class="animate-spin h-5 w-5 mr-3 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-blue-800">${message}</span>
            </div>
        </div>
    `;
}

function hideLoading() {
    document.getElementById('alert-container').innerHTML = '';
}

function generateIdempotencyKey() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

function updateProgress(step) {
    currentStep = step;
    
    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById(`step${i}-indicator`);
        const line = document.getElementById(`step${i}-line`);
        
        if (i <= step) {
            indicator.classList.remove('bg-gray-300', 'text-gray-600');
            indicator.classList.add('bg-blue-600', 'text-white');
            if (line) {
                line.classList.remove('bg-gray-300');
                line.classList.add('bg-blue-600');
            }
        }
    }
}

// Step loading functions
function loadStep2(data) {
    updateProgress(2);
    document.querySelector('.bg-white.rounded-lg').innerHTML = document.getElementById('step2-template').innerHTML;
    populateStep2(data);
}

function loadStep3(summary) {
    updateProgress(3);
    document.querySelector('.bg-white.rounded-lg').innerHTML = document.getElementById('step3-template').innerHTML;
    populateStep3(summary);
}
</script>
@include('transactions.wizard.step2')
@include('transactions.wizard.step3')
@endpush
@endsection
