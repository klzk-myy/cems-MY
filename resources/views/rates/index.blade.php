<x-app-layout title="Exchange Rates">
    <div class="flex min-h-screen flex-col">
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-gray-900">Exchange Rates</h1>
                    @if($currentBranch)
                        <span class="text-sm text-gray-500">{{ $currentBranch->name }}</span>
                    @endif
                </div>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        @if($canSelectBranch)
                            <select id="branch-select" class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                                <option value="">All Branches</option>
                            </select>
                        @endif
                        <select id="date-select" class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                            @foreach($availableDates as $date)
                                <option value="{{ $date }}">{{ $date }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="fetch-rates-btn" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                            Fetch from API
                        </button>
                        <button id="copy-previous-btn" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                            Copy Previous
                        </button>
                    </div>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Rate Buy</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Rate Sell</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Spread</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($rates as $rate)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium text-gray-900">{{ $rate['currency_code'] }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                        {{ number_format((float)$rate['rate_buy'], 4) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                        {{ number_format((float)$rate['rate_sell'], 4) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                        {{ number_format((float)($rate['spread'] ?? 0), 2) }}%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $rate['source'] === 'manual' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
                                            {{ ucfirst($rate['source'] ?? 'api') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                        {{ $rate['fetched_at'] ? \Carbon\Carbon::parse($rate['fetched_at'])->format('H:i:s') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <button class="override-btn text-blue-600 hover:text-blue-800" data-currency="{{ $rate['currency_code'] }}">
                                            Override
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                        No rates available. Click "Fetch from API" to get current rates.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Override Modal -->
    <div id="override-modal" class="hidden fixed inset-0 bg-gray-900/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Override Rate</h3>
            <form id="override-form">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Currency</label>
                        <input type="text" id="override-currency" class="mt-1 w-full px-4 py-2.5 text-sm bg-gray-100 border border-[#e5e5e5] rounded-lg" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rate Buy</label>
                        <input type="text" name="rate_buy" id="override-rate-buy" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rate Sell</label>
                        <input type="text" name="rate_sell" id="override-rate-sell" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reason</label>
                        <textarea name="reason" id="override-reason" rows="2" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" id="cancel-override" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Save Override
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('fetch-rates-btn')?.addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Fetching...';

            try {
                const response = await fetch('/api/v1/rates/fetch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed: ' + data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }

            btn.disabled = false;
            btn.textContent = 'Fetch from API';
        });

        document.getElementById('copy-previous-btn')?.addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Copying...';

            try {
                const response = await fetch('/api/v1/rates/copy-previous', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed: ' + data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }

            btn.disabled = false;
            btn.textContent = 'Copy Previous';
        });

        document.querySelectorAll('.override-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const currency = this.dataset.currency;
                document.getElementById('override-currency').value = currency;
                document.getElementById('override-modal').classList.remove('hidden');
            });
        });

        document.getElementById('cancel-override')?.addEventListener('click', function() {
            document.getElementById('override-modal').classList.add('hidden');
        });

        document.getElementById('override-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const currency = document.getElementById('override-currency').value;
            const rateBuy = document.getElementById('override-rate-buy').value;
            const rateSell = document.getElementById('override-rate-sell').value;
            const reason = document.getElementById('override-reason').value;

            try {
                const response = await fetch(`/api/v1/rates/${currency}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ rate_buy: rateBuy, rate_sell: rateSell, reason })
                });
                const data = await response.json();
                if (data.success) {
                    document.getElementById('override-modal').classList.add('hidden');
                    location.reload();
                } else {
                    alert('Failed: ' + data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        });
    </script>
</main>
</x-app-layout>