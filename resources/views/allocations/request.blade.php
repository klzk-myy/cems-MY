<x-app-layout title="Request Stock">
    <div class="space-y-6">
        <x-page-header title="Request Stock" description="Request currency allocations from the branch pool">
            <x-slot:actions>
                <x-button href="{{ route('my-allocations.index') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <h3 class="text-sm font-semibold text-ink">Branch Pool</h3>
            <div class="mt-3">
                @include('allocations.partials.pool-summary')
            </div>
        </x-card>

        <x-card>
            <form method="POST" action="{{ route('my-allocations.request.store') }}"
                  x-data="allocationCreate"
                  data-pool='@json($poolAvailable)'
                  data-branch="{{ auth()->user()->branch_id }}">
                @csrf

                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Requested Amount</th>
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
                                    <input type="number" :name="'lines[' + index + '][quantity]'" step="0.0001" min="0.0001"
                                           x-model="line.quantity" required placeholder="0.0000"
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

                <x-select
                    name="counter_id"
                    label="Counter (optional)"
                    :options="$counters->pluck('name', 'id')->toArray()"
                    placeholder="Any counter"
                />
                <div class="flex gap-3 mt-6">
                    <x-button type="submit">Submit Request</x-button>
                    <x-button href="{{ route('my-allocations.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
