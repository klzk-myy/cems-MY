<x-app-layout title="Exchange Rates">
    <div class="flex min-h-screen flex-col">
        <header class="bg-surface shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-ink">Exchange Rates</h1>
                    @if($currentBranch)
                        <span class="text-sm text-ink-muted">{{ $currentBranch->name }}</span>
                    @endif
                </div>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        @if($canSelectBranch)
                            <x-select name="branch" placeholder="All Branches" class="px-4 py-2 text-sm bg-surface border border-border rounded-lg">
                                <option value="">All Branches</option>
                            </x-select>
                        @endif
                        <x-select name="date" placeholder="Select Date" class="px-4 py-2 text-sm bg-surface border border-border rounded-lg">
                            @foreach($availableDates as $date)
                                <option value="{{ $date }}">{{ $date }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-button id="fetch-rates-btn" variant="primary">Fetch from API</x-button>
                        <x-button id="copy-previous-btn" variant="secondary">Copy Previous</x-button>
                    </div>
                </div>

                <div class="bg-surface border border-border rounded-xl overflow-hidden">
                    <table class="min-w-full divide-y divide-border">
                        <thead class="bg-canvas-subtle">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Rate Buy</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Rate Sell</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Spread</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Source</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Updated</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-surface divide-y divide-border">
                            @forelse($rates as $rate)
                                <tr class="hover:bg-canvas-subtle">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium text-ink">{{ $rate['currency_code'] }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-ink">
                                        {{ number_format((float)$rate['rate_buy'], 4) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-ink">
                                        {{ number_format((float)$rate['rate_sell'], 4) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-ink-muted">
                                        {{ number_format((float)($rate['spread'] ?? 0), 2) }}%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <x-badge variant="{{ $rate['source'] === 'manual' ? 'warning' : 'success' }}">
                                            {{ ucfirst($rate['source'] ?? 'api') }}
                                        </x-badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-ink-muted">
                                        {{ $rate['fetched_at'] ? \Carbon\Carbon::parse($rate['fetched_at'])->format('H:i:s') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <button class="override-btn text-blue-600 hover:text-blue-800" data-currency="{{ $rate['currency_code'] }}">
                                            Override
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <x-empty-state message="No rates available. Click "Fetch from API" to get current rates." :colspan="7" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Override Modal -->
    <div id="override-modal" class="hidden fixed inset-0 bg-gray-900/50 flex items-center justify-center z-50">
        <div class="bg-surface rounded-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-medium text-ink mb-4">Override Rate</h3>
            <form id="override-form" method="POST" action="{{ route('rates.override') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted">Currency</label>
                        <x-input type="text" name="currency_code" id="override-currency" class="mt-1 w-full px-4 py-2.5 text-sm bg-canvas-subtle border border-border rounded-lg" readonly />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted">Rate Buy</label>
                        <x-input type="text" name="rate_buy" id="override-rate-buy" class="mt-1 w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted">Rate Sell</label>
                        <x-input type="text" name="rate_sell" id="override-rate-sell" class="mt-1 w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted">Reason</label>
                        <textarea name="reason" id="override-reason" rows="2" class="mt-1 w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-button type="button" id="cancel-override" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Save Override</x-button>
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
    </script>
</main>
</x-app-layout>