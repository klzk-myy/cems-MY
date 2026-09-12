<x-app-layout title="Exchange Rates">
    <div x-data="{
            showOverride: false,
            overrideCurrency: '',
            overrideBuy: '',
            overrideSell: '',
            openOverride(detail) {
                this.overrideCurrency = detail.currency || '';
                this.overrideBuy = detail.buy || '';
                this.overrideSell = detail.sell || '';
                this.showOverride = true;
            }
         }"
         @override-rate.window="openOverride($event.detail)"
         @keydown.escape.window="showOverride = false"
    >
        <div class="space-y-6">
            <x-page-header
                title="Exchange Rates"
                description="Current buy/sell rates, spreads and manual overrides"
            >
                <x-slot:actions>
                    @if($canSelectBranch)
                        <form method="GET" action="{{ route('rates.index') }}" class="flex items-center gap-2">
                            <x-select name="branch_id"
                                :options="['' => 'All branches'] + $branches->pluck('name', 'id')->toArray()"
                                :selected="$currentBranch?->id"
                                onchange="this.form.submit()"
                                inline />
                            <noscript><x-button type="submit" variant="secondary" size="sm">Go</x-button></noscript>
                        </form>
                    @endif
                    @if(count($availableDates) > 0)
                        <form method="POST" action="{{ route('rates.copy-previous') }}" class="flex items-center gap-2">
                            @csrf
                            @if($currentBranch)
                                <input type="hidden" name="branch_id" value="{{ $currentBranch->id }}">
                            @endif
                            <x-select name="date"
                                :options="$availableDates"
                                onchange="this.form.submit()"
                                inline />
                        </form>
                    @endif
                </x-slot:actions>
            </x-page-header>

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif
            @if(session('error'))
                <x-alert type="error">{{ session('error') }}</x-alert>
            @endif

            <x-card title="Current Rates">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Buy Rate</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Sell Rate</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Spread</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Updated</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($rates as $rate)
                            @php
                                $fetchedAt = $rate['fetched_at'] ? \Illuminate\Support\Carbon::parse($rate['fetched_at']) : null;
                                $isStale = $fetchedAt !== null
                                    && $fetchedAt->diffInHours(now()) >= config('cems.rate_staleness_hours', 8);
                            @endphp
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm font-medium">
                                    {{ $rate['currency_code'] }}
                                    @if($currencies->has($rate['currency_code']))
                                        <span class="text-ink-muted">· {{ $currencies->get($rate['currency_code']) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $rate['rate_buy'], 4) }}</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $rate['rate_sell'], 4) }}</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $rate['spread'], 2) }}%</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="{{ $isStale ? 'text-warning-text' : 'text-ink-muted' }}">
                                        {{ $fetchedAt?->format('Y-m-d H:i') ?? '—' }}
                                    </span>
                                    @if($isStale)
                                        <x-badge variant="warning" class="ml-2">Stale</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    <x-button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        :data-currency="$rate['currency_code']"
                                        :data-buy="$rate['rate_buy']"
                                        :data-sell="$rate['rate_sell']"
                                        @click="$dispatch('override-rate', {
                                            currency: $el.dataset.currency,
                                            buy: $el.dataset.buy,
                                            sell: $el.dataset.sell
                                        })"
                                    >Override</x-button>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No exchange rates configured yet." :colspan="6" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>

            {{-- Override Rate Modal --}}
            <div x-show="showOverride"
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 @click="showOverride = false"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="override-modal-title">
                <div class="bg-surface rounded-xl shadow-lg max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                    <div class="flex items-center justify-between px-5 py-3 border-b border-border">
                        <h3 id="override-modal-title" class="text-lg font-semibold text-ink">Override Rate</h3>
                        <button @click="showOverride = false" class="text-ink-muted hover:text-ink p-1" aria-label="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('rates.override') }}" id="override-form">
                        @csrf
                        @if($currentBranch)
                            <input type="hidden" name="branch_id" value="{{ $currentBranch->id }}">
                        @endif
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-ink-muted mb-1">Currency</label>
                                <x-input type="text" name="currency_code" x-model="overrideCurrency" readonly />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-ink-muted mb-1">Rate Buy</label>
                                <x-input type="number" step="0.0001" min="0.0001" name="rate_buy" required x-model="overrideBuy" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-ink-muted mb-1">Rate Sell</label>
                                <x-input type="number" step="0.0001" min="0.0001" name="rate_sell" required x-model="overrideSell" />
                            </div>
                            <p x-show="overrideBuy !== '' && overrideSell !== '' && parseFloat(overrideSell) <= parseFloat(overrideBuy)"
                               x-cloak
                               class="text-sm text-danger-text">
                                Sell rate should be higher than the buy rate.
                            </p>
                            <div>
                                <label class="block text-sm font-medium text-ink-muted mb-1">Effective Date</label>
                                <x-input type="date" name="effective_date" help="Leave blank to apply immediately." />
                            </div>
                            <x-textarea name="reason" label="Reason" rows="2"></x-textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-border">
                            <x-button type="button" @click="showOverride = false" variant="secondary">Cancel</x-button>
                            <x-button type="submit" variant="primary">Save Override</x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
