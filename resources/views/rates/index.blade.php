<x-app-layout title="Exchange Rates">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header title="Exchange Rates" :actions="true">
            <x-slot:actions>
                @if($currentBranch)
                    <span class="text-sm text-ink-muted">{{ $currentBranch->name }}</span>
                @endif
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            @if($canSelectBranch)
                <x-select name="branch" placeholder="All Branches" inline />
            @endif
            <x-select
                name="date"
                :options="array_combine($availableDates, $availableDates)"
                :selected="$availableDates[0] ?? null"
                placeholder=""
                inline
            />
            <x-button id="fetch-rates-btn" variant="primary" type="button">Fetch from API</x-button>
            <x-button id="copy-previous-btn" variant="secondary" type="button">Copy Previous</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Rate Buy</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Rate Sell</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Spread</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Source</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Updated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($rates as $rate)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">{{ $rate['currency_code'] }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink">{{ number_format((float)$rate['rate_buy'], 4) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink">{{ number_format((float)$rate['rate_sell'], 4) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float)($rate['spread'] ?? 0), 2) }}%</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <x-badge variant="{{ $rate['source'] === 'manual' ? 'warning' : 'success' }}">
                                    {{ ucfirst($rate['source'] ?? 'api') }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">
                                {{ $rate['fetched_at'] ? \Carbon\Carbon::parse($rate['fetched_at'])->format('H:i:s') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <x-button variant="ghost" size="sm" class="override-btn" data-currency="{{ $rate['currency_code'] }}">Override</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message='No rates available. Click "Fetch from API" to get current rates.' :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>

    <!-- Override Modal -->
    <div id="override-modal" class="hidden fixed inset-0 bg-canvas/80 backdrop-blur-sm flex items-center justify-center z-50">
        <x-card class="w-full max-w-md">
            <div class="p-6">
                <h3 class="text-lg font-medium text-ink mb-4">Override Rate</h3>
                <form id="override-form" method="POST" action="{{ route('rates.override') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-ink-muted">Currency</label>
                            <x-input type="text" name="currency_code" id="override-currency" inline class="mt-1 w-full" readonly />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-muted">Rate Buy</label>
                            <x-input type="text" name="rate_buy" id="override-rate-buy" inline class="mt-1 w-full" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-muted">Rate Sell</label>
                            <x-input type="text" name="rate_sell" id="override-rate-sell" inline class="mt-1 w-full" required />
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
        </x-card>
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
</x-app-layout>
