<x-app-layout title="Quote Units">
    <div class="space-y-6">
        <x-page-header
            title="Quote Units"
            description="Set each currency's price multiply and unit-quoted buy/sell rate — e.g. 1,000,000 IDR = RM 235"
        >
            <x-slot:actions>
                @if($canSelectBranch)
                    <form method="GET" action="{{ route('rates.units') }}" class="flex items-center gap-2">
                        <x-select name="branch_id"
                            :options="['' => 'All branches'] + $branches->pluck('name', 'id')->toArray()"
                            :selected="$currentBranch?->id"
                            data-autosubmit
                            inline />
                        <noscript><x-button type="submit" variant="secondary" size="sm">Go</x-button></noscript>
                    </form>
                @endif
                <a href="{{ route('rates.index') }}">
                    <x-button type="button" variant="secondary" size="sm">Back to Rates</x-button>
                </a>
            </x-slot:actions>
        </x-page-header>

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @if(session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif

        <x-card title="Currency Quote Units">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Direction</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Quote Unit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Buy (per unit)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Sell (per unit)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Card Updated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($currencies as $currency)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium">
                                {{ $currency['code'] }}
                                <span class="text-ink-muted">· {{ $currency['name'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm w-44">
                                <x-select name="rate_inverse" inline required
                                    :options="['0' => 'Direct (MYR per unit)', '1' => 'Inverse (per RM unit)']"
                                    :selected="$currency['rate_inverse'] ? '1' : '0'"
                                    form="unit-form-{{ $currency['code'] }}" />
                            </td>
                            <td class="px-4 py-3 text-sm w-36">
                                <x-input type="number" name="rate_unit" min="1" step="1"
                                    :value="$currency['rate_unit']" required inline
                                    form="unit-form-{{ $currency['code'] }}" />
                            </td>
                            <td class="px-4 py-3 text-sm w-44">
                                <x-input type="number" name="rate_buy" step="any" min="0.0001"
                                    :value="$currency['rate_buy']" placeholder="e.g. 235" inline
                                    form="unit-form-{{ $currency['code'] }}" />
                            </td>
                            <td class="px-4 py-3 text-sm w-44">
                                <x-input type="number" name="rate_sell" step="any" min="0.0001"
                                    :value="$currency['rate_sell']" placeholder="e.g. 245" inline
                                    form="unit-form-{{ $currency['code'] }}" />
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">
                                {{ $currency['fetched_at'] ?? '—' }}
                                @unless($currency['has_card'])
                                    <x-badge variant="warning" class="ml-2">No card</x-badge>
                                @endunless
                            </td>
                            <td class="px-4 py-3 text-sm text-right">
                                <form id="unit-form-{{ $currency['code'] }}" method="POST"
                                      action="{{ route('rates.units.update') }}">
                                    @csrf
                                    <input type="hidden" name="currency_code" value="{{ $currency['code'] }}">
                                    @if($currentBranch)
                                        <input type="hidden" name="branch_id" value="{{ $currentBranch->id }}">
                                    @endif
                                    <x-button type="submit" variant="primary" size="sm">Save</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No active currencies configured." :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
            <p class="mt-3 text-sm text-ink-muted">
                Direct: Buy/Sell are quoted in MYR per quote unit (1,000,000 IDR = RM 235). Inverse: quoted in
                foreign units per RM unit (RM 1 = 4,255 IDR) — buy is the larger number. Changing a unit or
                direction re-quotes how the card is displayed; saving a row writes the card in the new
                convention, so the rate inputs clear when the convention changes. Existing transactions are
                unaffected.
            </p>
        </x-card>
    </div>
    <script>
        // Rate values are quoted under the submitted convention, so a changed
        // unit or direction must not carry the pre-filled old-convention
        // numbers into the save — clear them to force re-entry.
        document.querySelectorAll('[name="rate_unit"], [name="rate_inverse"]').forEach(function (el) {
            el.addEventListener('change', function () {
                var formId = el.getAttribute('form');
                if (! formId) return;
                document.querySelectorAll('[form="' + formId + '"]').forEach(function (input) {
                    if (input.name === 'rate_buy' || input.name === 'rate_sell') {
                        input.value = '';
                    }
                });
            });
        });
    </script>
</x-app-layout>
