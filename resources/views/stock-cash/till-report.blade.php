<x-app-layout title="Till Report - {{ $date }}">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Till Report</h1>
                <p class="mt-1 text-sm text-ink-muted">Report Date: {{ $date }}</p>
            </div>
            <a href="{{ url()->previous() }}"
               class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-canvas-subtle">
                Back
            </a>
        </div>

        <!-- Till Balance Details -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h2 class="text-lg font-medium text-ink mb-4">Till Balance Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Till / Counter</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $balances->counter->name ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Opening Float</dt>
                    <dd class="mt-1 text-sm text-ink">
                        MYR {{ number_format((float) $balances->opening_float, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Closing Float</dt>
                    <dd class="mt-1 text-sm text-ink">
                        MYR {{ number_format((float) $balances->closing_float, 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Opening By</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $balances->opener->name ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Closing By</dt>
                    <dd class="mt-1 text-sm text-ink">
                        {{ $balances->closer->name ?? 'N/A' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Status</dt>
                    <dd class="mt-1 text-sm">
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded
                            @if($balances->status->value === 'closed')
                                bg-green-100 text-green-700
                            @elseif($balances->status->value === 'open')
                                bg-blue-100 text-blue-700
                            @else
                                bg-canvas-subtle text-gray-700
                            @endif">
                            {{ ucfirst($balances->status->value) }}
                        </span>
                    </dd>
                </div>
            </div>
        </div>

        <!-- Currency Balances Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h2 class="text-lg font-medium text-ink mb-4">Currency Balances</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#e5e5e5]">
                    <thead>
                        <tr class="bg-canvas-subtle">
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Opening Balance</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Total Bought</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Total Sold</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Closing Balance</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Variance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($balances->currencyBalances ?? [] as $currencyBalance)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink">
                                    {{ $currencyBalance->currency->code ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-ink text-right">
                                    {{ number_format((float) $currencyBalance->opening_balance, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-ink text-right">
                                    {{ number_format((float) $currencyBalance->total_bought, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-ink text-right">
                                    {{ number_format((float) $currencyBalance->total_sold, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-ink text-right">
                                    {{ number_format((float) $currencyBalance->closing_balance, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    @php
                                        $variance = (float) $currencyBalance->variance;
                                    @endphp
                                    <span class="@if($variance != 0) text-red-600 font-medium @else text-ink @endif">
                                        {{ number_format($variance, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-ink-muted">
                                    No currency balances found for this till.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
</x-app-layout>
