<x-app-layout title="Exchange Rates">
    <x-page-header title="Exchange Rates" description="View and manage currency rates" />

    @if($canSelectBranch)
        <form method="GET" action="{{ route('rates.index') }}" class="mb-6 flex items-end gap-4">
            <div>
                <label for="branch_id" class="mb-1 block text-sm font-medium text-ink">Branch</label>
                <select id="branch_id" name="branch_id" class="mt-1">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($currentBranch !== null && $currentBranch->id === (int) $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>
    @endif

    <x-card>
        <form id="override-form" method="POST" action="{{ route('rates.override') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-select name="currency_code" label="Currency" :options="['USD' => 'USD', 'EUR' => 'EUR', 'SGD' => 'SGD']" :required="true" />
                <x-input name="rate_buy" label="Buy Rate" type="number" :required="true" />
                <x-input name="rate_sell" label="Sell Rate" type="number" :required="true" />
            </div>
            <x-textarea name="reason" label="Reason for Override" :required="true" />
            <div class="flex justify-end">
                <x-button type="submit" variant="primary">Override Rates</x-button>
            </div>
        </form>
    </x-card>

    <div class="mt-6">
        <x-card title="Current Rates">
            @if(count($rates) === 0)
                <x-empty-state title="No exchange rates configured yet." description="Rates will appear here once set for the day." />
            @else
                <x-table>
                    <x-slot:thead>
                        <tr>
                            <th class="px-4 py-3">Currency</th>
                            <th class="px-4 py-3">Buy Rate</th>
                            <th class="px-4 py-3">Sell Rate</th>
                            <th class="px-4 py-3">Updated</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @foreach($rates as $rate)
                            @php
                                $fetchedAt = $rate['fetched_at'] !== null ? \Illuminate\Support\Carbon::parse($rate['fetched_at']) : null;
                                $isStale = $fetchedAt !== null
                                    && $fetchedAt->diffInHours(now()) > (int) config('cems.rate_staleness_hours', 8);
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-ink">{{ $rate['currency_code'] }}</td>
                                <td class="px-4 py-3 text-sm text-ink">{{ number_format((float) $rate['rate_buy'], 4) }}</td>
                                <td class="px-4 py-3 text-sm text-ink">{{ number_format((float) $rate['rate_sell'], 4) }}</td>
                                <td class="px-4 py-3 text-sm text-ink-muted">{{ $fetchedAt?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($isStale)
                                        <x-badge variant="warning">Stale</x-badge>
                                    @else
                                        <x-badge variant="success">Current</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:tbody>
                </x-table>
            @endif
        </x-card>
    </div>
</x-app-layout>
