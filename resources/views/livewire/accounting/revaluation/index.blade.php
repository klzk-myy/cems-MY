<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Currency Revaluation</h1>
        <p class="text-sm text-gray-500">Monthly exchange rate revaluation for currency positions</p>
    </div>

    {{-- Header Actions --}}
    <div class="flex justify-end mb-6">
        <button
            wire:click="runRevaluation"
            @disabled($isRunning)
            class="btn btn-primary"
        >
            @if($isRunning)
                <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Running...
            @else
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Run Revaluation
            @endif
        </button>
    </div>

    {{-- Run Message --}}
    @if($runMessage)
        <div class="mb-6 p-4 rounded-lg {{ str_contains($runMessage, 'failed') ? 'bg-red-600' : 'bg-green-600' }} bg-opacity-10 text-gray-900">
            <p class="text-sm">{{ $runMessage }}</p>
        </div>
    @endif

    {{-- Positions Table --}}
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Currency</th>
                        <th>Balance</th>
                        <th>Current Rate</th>
                        <th>Previous Rate</th>
                        <th>Unrealized P/L</th>
                        <th>Status</th>
                        <th>Last Valuation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($positions as $position)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-medium">{{ $position['currency_code'] }}</span>
                                <span class="text-gray-500 text-sm">{{ $position['currency_name'] }}</span>
                            </div>
                        </td>
                        <td class="font-mono">{{ number_format((float) $position['balance'], 4) }}</td>
                        <td class="font-mono">{{ number_format((float) $position['current_rate'], 6) }}</td>
                        <td class="font-mono">{{ number_format((float) $position['previous_rate'], 6) }}</td>
                        <td class="font-mono {{ (float) $position['unrealized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format((float) $position['unrealized_pnl'], 2) }} MYR
                        </td>
                        <td>
                            @if($position['needs_revaluation'])
                                <span class="badge badge-warning">Pending</span>
                            @else
                                <span class="badge badge-success">Current</span>
                            @endif
                        </td>
                        <td class="text-gray-500 text-sm">
                            @if($position['last_valuation_at'])
                                {{ \Carbon\Carbon::parse($position['last_valuation_at'])->format('d M Y H:i') }}
                            @else
                                Never
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-12 text-gray-500">No currency positions found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
