<x-app-layout title="New Allocation">
    <div class="space-y-6">
        <x-page-header title="New Allocation" description="Assign stock from the branch pool to a teller">
            <x-slot:actions>
                <x-button href="{{ route('allocations.index') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <h3 class="text-sm font-semibold text-ink">Branch Pool</h3>
            <div class="mt-3">
                @include('allocations.partials.pool-summary')
            </div>
        </x-card>

        <x-card>
            <form method="POST" action="{{ route('allocations.store') }}"
                  x-data="allocationCreate"
                  data-pool='@json($poolAvailable)'
                  data-teller-branches='@json($tellerBranches)'
                  data-initial-user="{{ old('user_id') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select
                        name="user_id"
                        label="Teller"
                        :options="$tellers->mapWithKeys(fn ($t) => [$t->id => $t->username])->toArray()"
                        placeholder="Select a teller"
                        x-model="userId"
                        required
                    />
                    <x-input
                        name="daily_limit_myr"
                        label="Daily Limit (MYR, optional)"
                        type="number"
                        step="0.01"
                        min="0"
                    />
                </div>

                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Remove</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        <template x-for="(line, index) in lines" :key="index">
                            <tr>
                                <td class="px-4 py-3">
                                    <select
                                        :name="'lines[' + index + '][currency_code]'"
                                        x-model="line.currency"
                                        required
                                        class="w-full rounded-md border-border bg-surface text-ink text-sm focus:border-primary focus:ring-primary"
                                    >
                                        <option value="">Select a currency</option>
                                        @foreach($currencies as $currency)
                                            <option value="{{ $currency->code }}">{{ $currency->code }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-ink-muted"
                                       x-show="line.currency && availableFor(line.currency) !== null"
                                       x-text="'Pool available: ' + availableFor(line.currency)"></p>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" :name="'lines[' + index + '][amount]'" step="0.0001" min="0.0001"
                                           x-model="line.amount" required placeholder="0.0000"
                                           class="w-full rounded-md border-border bg-surface text-ink text-sm text-right focus:border-primary focus:ring-primary" />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-button type="button" variant="danger" size="sm" @click="removeLine(index)" x-bind:disabled="lines.length <= 1">Remove</x-button>
                                </td>
                            </tr>
                        </template>
                    </x-slot:tbody>
                </x-table>
                <div class="px-4 py-3 border-t border-border">
                    <x-button type="button" @click="addLine()" variant="secondary">+ Add Currency</x-button>
                </div>

                <p class="mt-3 text-xs text-ink-muted">
                    Funds move from the branch pool immediately. The teller confirms receipt via Accept on My Allocations.
                </p>
                <div class="flex gap-3 mt-6">
                    <x-button type="submit">Allocate Stock</x-button>
                    <x-button href="{{ route('allocations.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
