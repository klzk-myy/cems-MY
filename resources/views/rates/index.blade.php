@extends('layouts.base')

@section('title', 'Exchange Rates - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Exchange Rates</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Currency rates and spread management</p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="fetchRates()" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
            Fetch Latest
        </button>
        <button onclick="copyPreviousRates()" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
            Copy Previous
        </button>
    </div>
</div>

@if(session('success'))
<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
    {{ session('error') }}
</div>
@endif

@if($currentBranch)
<div class="mb-4 p-3 bg-[--color-canvas-subtle] rounded-lg text-sm text-[--color-ink-muted]">
    Branch: {{ $currentBranch->name }}
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    @forelse($rates as $rate)
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-[--color-ink]">{{ $rate['currency_code'] }}</h3>
            <span class="text-xs text-[--color-ink-muted]">{{ $rate['source'] }}</span>
        </div>
        <div class="space-y-3">
            <div class="flex justify-between items-center">
                <span class="text-sm text-[--color-ink-muted]">Buy Rate</span>
                <span class="text-xl font-bold text-[--color-ink]">{{ number_format((float)$rate['rate_buy'], 4) }}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-sm text-[--color-ink-muted]">Sell Rate</span>
                <span class="text-xl font-bold text-[--color-ink]">{{ number_format((float)$rate['rate_sell'], 4) }}</span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-[--color-border]">
                <span class="text-sm text-[--color-ink-muted]">Spread</span>
                <span class="text-sm font-medium text-[--color-accent]">{{ $rate['spread'] }}%</span>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-[--color-border] text-xs text-[--color-ink-muted]">
            Updated: {{ $rate['fetched_at'] ? \Carbon\Carbon::parse($rate['fetched_at'])->format('Y-m-d H:i') : 'N/A' }}
        </div>
    </div>
    @empty
    <div class="col-span-3 card">
        <div class="p-8 text-center text-[--color-ink-muted]">
            No exchange rates configured. Click "Fetch Latest" to download rates from the API.
        </div>
    </div>
    @endforelse
</div>

@if(count($availableDates) > 0)
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Rate History</h3>
    </div>
    <div class="p-6">
        <div class="flex items-center gap-4">
            <label class="text-sm text-[--color-ink-muted]">Select Date:</label>
            <select id="historyDate" class="px-3 py-2 border border-[--color-border] rounded-lg text-sm">
                @foreach($availableDates as $date)
                <option value="{{ $date }}">{{ $date }}</option>
                @endforeach
            </select>
            <button onclick="loadHistory()" class="px-4 py-2 bg-[--color-canvas-subtle] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-border]">
                Load History
            </button>
        </div>
        <div id="historyResults" class="mt-4"></div>
    </div>
</div>
@endif

<script>
function fetchRates() {
    fetch('/api/v1/rates/fetch', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to fetch rates: ' + data.message);
        }
    })
    .catch(error => alert('Error: ' + error));
}

function copyPreviousRates() {
    const date = prompt('Enter date to copy rates from (YYYY-MM-DD):', '{{ now()->subDay()->format("Y-m-d") }}');
    if (date) {
        fetch('/api/v1/rates/copy-previous', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ date: date }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to copy rates: ' + data.message);
            }
        })
        .catch(error => alert('Error: ' + error));
    }
}

function loadHistory() {
    const date = document.getElementById('historyDate').value;
    const container = document.getElementById('historyResults');
    container.innerHTML = '<div class="text-[--color-ink-muted]">Loading...</div>';

    fetch(`/api/v1/rates/history/${date}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let html = '<table class="table mt-4"><thead><tr><th>Currency</th><th>Rate</th></tr></thead><tbody>';
                data.data.forEach(item => {
                    html += `<tr><td>${item.currency_code}</td><td>${item.rate}</td></tr>`;
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-[--color-ink-muted]">No history found for this date</div>';
            }
        })
        .catch(error => container.innerHTML = '<div class="text-red-600">Error loading history</div>');
}
</script>
@endsection