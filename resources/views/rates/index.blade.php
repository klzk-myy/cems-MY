@extends('layouts.base')

@section('title', 'Exchange Rates')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Exchange Rates</h1>
    <p class="text-sm text-[--color-ink-muted]">Daily rate management for currency trading</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    @if(auth()->user()->role->isManager() || auth()->user()->role->isAdmin())
    <button onclick="openCopyModal()" class="btn btn-secondary">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
        </svg>
        Copy Previous
    </button>
    <button onclick="fetchRates()" class="btn btn-primary" id="btn-fetch" >
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Fetch from API
    </button>
    @endif
</div>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Status Alert -->
    @if(empty($rates))
    <div class="alert alert-warning">
        <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <div class="alert-content">
            <p class="alert-title">No Rates Set</p>
            <p class="alert-description">Fetch rates from the API or copy from a previous date to begin trading.</p>
        </div>
    </div>
    @endif

    <!-- Rates Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Current Exchange Rates</h3>
            <span class="badge badge-info">{{ count($rates) }} currencies</span>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Currency</th>
                        <th class="text-right">Buy Rate</th>
                        <th class="text-right">Sell Rate</th>
                        <th class="text-right">Spread</th>
                        <th>Source</th>
                        <th>Last Updated</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rates as $rate)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-[--color-accent] rounded-lg flex items-center justify-center">
                                    <span class="text-white font-bold text-sm">{{ substr($rate['currency_code'], 0, 1) }}</span>
                                </div>
                                <span class="font-medium">{{ $rate['currency_code'] }}</span>
                            </div>
                        </td>
                        <td class="text-right font-mono">{{ number_format((float)$rate['rate_buy'], 4) }}</td>
                        <td class="text-right font-mono">{{ number_format((float)$rate['rate_sell'], 4) }}</td>
                        <td class="text-right">
                            <span class="badge badge-success">{{ $rate['spread'] }}%</span>
                        </td>
                        <td>
                            @php
                                $sourceLabel = match(true) {
                                    str_contains($rate['source'] ?? '', 'api') => 'API',
                                    str_contains($rate['source'] ?? '', 'copied') => 'Copied',
                                    str_contains($rate['source'] ?? '', 'override') => 'Manual',
                                    default => 'Manual'
                                };
                                $sourceClass = match(true) {
                                    str_contains($rate['source'] ?? '', 'api') => 'badge-info',
                                    str_contains($rate['source'] ?? '', 'copied') => 'badge-warning',
                                    str_contains($rate['source'] ?? '', 'override') => 'badge-accent',
                                    default => 'badge-default'
                                };
                            @endphp
                            <span class="badge {{ $sourceClass }}">{{ $sourceLabel }}</span>
                        </td>
                        <td class="text-sm text-[--color-ink-muted]">
                            {{ $rate['fetched_at'] ? \Carbon\Carbon::parse($rate['fetched_at'])->format('d M Y, H:i') : 'N/A' }}
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
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
                        <td colspan="7" class="text-center py-8 text-[--color-ink-muted]">
                            No exchange rates available
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- History Panel -->
    <div class="card" id="history-panel" style="display: none;">
        <div class="card-header">
            <h3 class="card-title">Rate History: <span id="history-currency"></span></h3>
            <button onclick="closeHistory()" class="btn btn-ghost btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Mid Rate</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody id="history-table-body">
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Copy Previous Modal -->
<div id="copy-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeCopyModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-[--content-bg] rounded-xl shadow-2xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold">Copy Previous Rates</h3>
            <button onclick="closeCopyModal()" class="btn btn-ghost btn-sm p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form onsubmit="copyPreviousRates(event)">
            <div class="space-y-4">
                <div>
                    <label class="form-label">Select Date</label>
                    <select name="copy_date" id="copy_date" class="form-input">
                        @foreach($availableDates as $date)
                        <option value="{{ $date }}">{{ $date }}</option>
                        @endforeach
                    </select>
                    <p class="form-hint">Rates will be copied as both buy and sell rates</p>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeCopyModal()" class="btn btn-secondary flex-1">Cancel</button>
                <button type="submit" class="btn btn-primary flex-1">Copy Rates</button>
            </div>
        </form>
    </div>
</div>

<!-- Override Modal -->
<div id="override-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeOverrideModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-[--content-bg] rounded-xl shadow-2xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold">Override Rate: <span id="override-currency"></span></h3>
            <button onclick="closeOverrideModal()" class="btn btn-ghost btn-sm p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form onsubmit="overrideRate(event)">
            <input type="hidden" name="override_currency" id="override_currency">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Buy Rate</label>
                        <input type="number" name="override_buy" id="override_buy" step="0.0001" min="0.0001" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Sell Rate</label>
                        <input type="number" name="override_sell" id="override_sell" step="0.0001" min="0.0001" class="form-input" required>
                    </div>
                </div>
                <div>
                    <label class="form-label">Reason (optional)</label>
                    <textarea name="override_reason" id="override_reason" rows="2" class="form-input" placeholder="e.g., Special rate for VIP client"></textarea>
                </div>
                <div id="override-error" class="text-danger text-sm" style="display: none;"></div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeOverrideModal()" class="btn btn-secondary flex-1">Cancel</button>
                <button type="submit" class="btn btn-primary flex-1">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function fetchRates() {
    const btn = document.getElementById('btn-fetch');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Fetching...';

    try {
        const response = await fetch('/api/v1/rates/fetch', {
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
            btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Fetch from API';
        }
    } catch (error) {
        showNotification('Error fetching rates: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Fetch from API';
    }
}

function getAuthToken() {
    return fetch('/sanctum/csrf-cookie').then(() => {
        return localStorage.getItem('auth_token') || '';
    });
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

    try {
        const response = await fetch('/api/v1/rates/copy-previous', {
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
            showNotification('Rates copied successfully from ' + date, 'success');
            closeCopyModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(result.message || 'Failed to copy rates', 'error');
        }
    } catch (error) {
        showNotification('Error copying rates: ' + error.message, 'error');
    }
}

function openOverrideModal(currency, currentBuy, currentSell) {
    document.getElementById('override-currency').textContent = currency;
    document.getElementById('override_currency').value = currency;
    document.getElementById('override_buy').value = currentBuy;
    document.getElementById('override_sell').value = currentSell;
    document.getElementById('override_reason').value = '';
    document.getElementById('override-error').style.display = 'none';
    document.getElementById('override-modal').classList.remove('hidden');
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

    try {
        const response = await fetch(`/api/v1/rates/${currency}`, {
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
            showNotification(`Rate for ${currency} overridden successfully`, 'success');
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
    const tbody = document.getElementById('history-table-body');

    panel.style.display = 'block';
    currencySpan.textContent = currency;
    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4">Loading...</td></tr>';

    try {
        const response = await fetch(`/api/v1/rates/history/${currency}?days=30`, {
            headers: {
                'Authorization': 'Bearer ' + (await getAuthToken())
            }
        });
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            tbody.innerHTML = result.data.map(h => `
                <tr>
                    <td>${new Date(h.effective_date).toLocaleDateString()}</td>
                    <td class="text-right font-mono">${parseFloat(h.rate).toFixed(4)}</td>
                    <td class="text-sm text-[--color-ink-muted]">${h.notes || '-'}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-[--color-ink-muted]">No history available</td></tr>';
        }
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-red-500">Error loading history</td></tr>';
    }
}

function closeHistory() {
    document.getElementById('history-panel').style.display = 'none';
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg animate-slideUp ${
        type === 'success' ? 'bg-green-600 text-white' :
        type === 'error' ? 'bg-red-600 text-white' :
        'bg-blue-600 text-white'
    }`;
    notification.innerHTML = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
@endpush