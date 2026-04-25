@extends('layouts.base')

@section('title', 'Exchange Rates')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Exchange Rates</h1>
    <p class="text-sm text-[--color-ink-muted]">
        @if($currentBranch)
            {{ $currentBranch->name }} ({{ $currentBranch->code }})
        @else
            All Branches
        @endif
    </p>
</div>
@endsection

@section('header-actions')
@if($canSelectBranch)
<select id="branch-select" onchange="changeBranch(this.value)" class="form-select text-sm">
    <option value="">-- Select Branch --</option>
</select>
@endif
<button onclick="openCopyModal()" class="btn btn-secondary text-sm">
    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
    </svg>
    Copy Previous
</button>
<button onclick="fetchRates()" id="btn-fetch" class="btn btn-primary text-sm">
    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
    </svg>
    Fetch from API
</button>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Current Exchange Rates</h3>
        <span class="badge">{{ count($rates) }} currencies</span>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th>Buy Rate</th>
                    <th>Sell Rate</th>
                    <th>Spread</th>
                    <th>Source</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rates as $rate)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center font-bold text-xs">
                                {{ substr($rate['currency_code'], 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium">{{ $rate['currency_code'] }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="font-mono">{{ $rate['rate_buy'] }}</td>
                    <td class="font-mono">{{ $rate['rate_sell'] }}</td>
                    <td>
                        <span class="badge @if(($rate['spread'] ?? 0) > 1) badge-warning @else badge-default @endif">
                            {{ $rate['spread'] ?? 0 }}%
                        </span>
                    </td>
                    <td>
                        <span class="badge @if(str_contains($rate['source'] ?? '', 'override')) badge-accent @else badge-default @endif">
                            {{ str_contains($rate['source'] ?? '', 'override') ? 'Manual' : ucfirst($rate['source'] ?? 'N/A') }}
                        </span>
                    </td>
                    <td>{{ $rate['fetched_at'] ? \Carbon\Carbon::parse($rate['fetched_at'])->format('d M Y, H:i') : 'N/A' }}</td>
                    <td>
                        <div class="flex items-center gap-1">
                            <button onclick="viewHistory('{{ $rate['currency_code'] }}')" class="btn btn-ghost btn-sm" title="View History">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </button>
                            @if(auth()->user()->role->isManager() || auth()->user()->role->isAdmin())
                            <button onclick="openOverrideModal('{{ $rate['currency_code'] }}', '{{ $rate['rate_buy'] }}', '{{ $rate['rate_sell'] }}')" class="btn btn-ghost btn-sm" title="Override Rate">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-8 text-[--color-ink-muted]">No exchange rates available</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

{{-- Modals --}}
<div id="copy-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/70" onclick="closeCopyModal()"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl border border-gray-200 max-w-md w-full">
            <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <h3 class="text-lg font-bold text-gray-900">Copy Previous Rates</h3>
                <button onclick="closeCopyModal()" class="btn btn-ghost btn-sm p-1 text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form onsubmit="copyPreviousRates(event)" class="p-5">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Select Date</label>
                        <select name="copy_date" id="copy_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            @foreach($availableDates as $date)
                            <option value="{{ $date }}">{{ $date }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Rates will be copied as both buy and sell rates</p>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeCopyModal()" class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-lg font-medium">Copy Rates</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="override-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/70" onclick="closeOverrideModal()"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl border border-gray-200 max-w-md w-full">
            <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <h3 class="text-lg font-bold text-gray-900">Override Rate: <span id="override-currency"></span></h3>
                <button onclick="closeOverrideModal()" class="btn btn-ghost btn-sm p-1 text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form onsubmit="overrideRate(event)" class="p-5">
                <input type="hidden" name="override_currency" id="override_currency">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Buy Rate</label>
                            <input type="number" name="override_buy" id="override_buy" step="0.0001" min="0.0001" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Sell Rate</label>
                            <input type="number" name="override_sell" id="override_sell" step="0.0001" min="0.0001" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Reason (optional)</label>
                        <textarea name="override_reason" id="override_reason" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="e.g., Special rate for VIP client"></textarea>
                    </div>
                    <div id="override-error" class="text-red-600 text-sm hidden"></div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeOverrideModal()" class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-lg font-medium">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="history-panel" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/70" onclick="closeHistoryPanel()"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl border border-gray-200 max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <h3 class="text-lg font-bold text-gray-900">Rate History: <span id="history-currency"></span></h3>
                <button onclick="closeHistoryPanel()" class="btn btn-ghost btn-sm p-1 text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-auto p-5">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Rate</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody id="history-table-body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let currentBranchId = '{{ $currentBranch?->id ?? '' }}';

document.addEventListener('DOMContentLoaded', function() {
    loadBranches();
});

async function loadBranches() {
    const select = document.getElementById('branch-select');
    if (!select) return;

    try {
        const response = await fetch('/api/v1/branches', {
            headers: {
                'Authorization': 'Bearer ' + (await getAuthToken())
            }
        });
        const result = await response.json();

        if (result.data) {
            select.innerHTML = '<option value="">-- Select Branch --</option>';
            result.data.forEach(branch => {
                const selected = branch.id == currentBranchId ? 'selected' : '';
                select.innerHTML += `<option value="${branch.id}" ${selected}>${branch.code} - ${branch.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Failed to load branches:', error);
    }
}

function changeBranch(branchId) {
    const url = new URL(window.location.href);
    if (branchId) {
        url.searchParams.set('branch_id', branchId);
    } else {
        url.searchParams.remove('branch_id');
    }
    window.location.href = url.toString();
}

function getBranchId() {
    const select = document.getElementById('branch-select');
    return select ? select.value : '';
}

async function fetchRates() {
    const btn = document.getElementById('btn-fetch');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Fetching...';

    try {
        const branchId = getBranchId();
        const url = '/api/v1/rates/fetch' + (branchId ? '?branch_id=' + branchId : '');

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Authorization': 'Bearer ' + (await getAuthToken())
            }
        });
        const result = await response.json();

        if (result.success) {
            showNotification('Rates fetched successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(result.message || 'Failed to fetch rates', 'error');
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Fetch from API';
        }
    } catch (error) {
        showNotification('Error: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Fetch from API';
    }
}

function openCopyModal() {
    document.getElementById('copy-modal').classList.remove('hidden');
}

function closeCopyModal() {
    document.getElementById('copy-modal').classList.add('hidden');
}

async function copyPreviousRates(event) {
    event.preventDefault();
    const date = document.getElementById('copy_date').value;
    const branchId = getBranchId();

    try {
        const url = '/api/v1/rates/copy-previous' + (branchId ? '?branch_id=' + branchId : '');
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Authorization': 'Bearer ' + (await getAuthToken())
            },
            body: JSON.stringify({ date })
        });
        const result = await response.json();

        if (result.success) {
            showNotification('Rates copied successfully', 'success');
            closeCopyModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(result.message || 'Failed to copy rates', 'error');
        }
    } catch (error) {
        showNotification('Error: ' + error.message, 'error');
    }
}

function openOverrideModal(currency, currentBuy, currentSell) {
    document.getElementById('override-currency').textContent = currency;
    document.getElementById('override_currency').value = currency;
    document.getElementById('override_buy').value = currentBuy;
    document.getElementById('override_sell').value = currentSell;
    document.getElementById('override_reason').value = '';
    document.getElementById('override-error').style.display = 'none';
    const modal = document.getElementById('override-modal');
    modal.classList.remove('hidden');
}

function closeOverrideModal() {
    document.getElementById('override-modal').classList.add('hidden');
}

async function overrideRate(event) {
    event.preventDefault();
    const currency = document.getElementById('override_currency').value;
    const buyRate = document.getElementById('override_buy').value;
    const sellRate = document.getElementById('override_sell').value;
    const reason = document.getElementById('override_reason').value;
    const errorDiv = document.getElementById('override-error');
    const branchId = getBranchId();

    try {
        const url = '/api/v1/rates/' + currency + (branchId ? '?branch_id=' + branchId : '');
        const response = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Authorization': 'Bearer ' + (await getAuthToken())
            },
            body: JSON.stringify({
                rate_buy: buyRate,
                rate_sell: sellRate,
                reason: reason
            })
        });
        const result = await response.json();

        if (result.success) {
            showNotification('Rate for ' + currency + ' overridden successfully', 'success');
            closeOverrideModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            errorDiv.textContent = result.message;
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'Error: ' + error.message;
        errorDiv.style.display = 'block';
    }
}

async function viewHistory(currency) {
    const panel = document.getElementById('history-panel');
    const currencySpan = document.getElementById('history-currency');
    const tableBody = document.getElementById('history-table-body');
    const branchId = getBranchId();

    currencySpan.textContent = currency;
    tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-4">Loading...</td></tr>';
    panel.classList.remove('hidden');

    try {
        const url = '/api/v1/rates/' + currency + '/history' + (branchId ? '?branch_id=' + branchId : '');
        const response = await fetch(url, {
            headers: {
                'Authorization': 'Bearer ' + (await getAuthToken())
            }
        });
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            tableBody.innerHTML = result.data.map(h => `
                <tr>
                    <td>${new Date(h.effective_date).toLocaleDateString()}</td>
                    <td class="font-mono">${h.rate}</td>
                    <td>${h.source || 'N/A'}</td>
                </tr>
            `).join('');
        } else {
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-[--color-ink-muted]">No history found</td></tr>';
        }
    } catch (error) {
        tableBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-red-600">Error loading history</td></tr>';
    }
}

function closeHistoryPanel() {
    document.getElementById('history-panel').classList.add('hidden');
}

async function getAuthToken() {
    return localStorage.getItem('auth_token') || '';
}

function showNotification(message, type) {
    const container = document.getElementById('notification-container') || createNotificationContainer();
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg animate-slideUp ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        'bg-gray-800 text-white'
    }`;
    notification.textContent = message;
    container.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function createNotificationContainer() {
    const container = document.createElement('div');
    container.id = 'notification-container';
    document.body.appendChild(container);
    return container;
}
</script>
@endpush
