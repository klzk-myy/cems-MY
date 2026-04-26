<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Stock & Cash</h1>
        <p class="text-sm text-gray-500">Currency position and cash management</p>
    </div>

    {{-- Position Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        {{-- MYR Cash Card --}}
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon bg-green-600/10 text-green-600">
                    <span class="font-bold">MYR</span>
                </div>
            </div>
            <p class="stat-card-label">Cash in Hand</p>
            <p class="stat-card-value">{{ number_format((float) $myrCashInHand, 2) }}</p>
            <p class="stat-card-change text-gray-500">Ringgit Malaysia</p>
        </div>

        {{-- Foreign Currency Cards --}}
        @foreach($positions as $position)
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon bg-blue-500/10 text-blue-500">
                    <span class="font-bold">{{ $position['currency_code'] }}</span>
                </div>
            </div>
            <p class="stat-card-label">{{ $position['currency_name'] }}</p>
            <p class="stat-card-value">{{ number_format((float) $position['quantity'], 2) }}</p>
            <p class="stat-card-change text-gray-500">
                Avg Cost: {{ number_format((float) $position['avg_cost'], 4) }}
            </p>
        </div>
        @endforeach
    </div>

    {{-- Stats Summary --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="p-4 bg-gray-50 rounded">
            <dt class="text-sm text-gray-500">Active Positions</dt>
            <dd class="text-2xl font-mono">{{ $stats['active_positions'] ?? 0 }}</dd>
        </div>
        <div class="p-4 bg-gray-50 rounded">
            <dt class="text-sm text-gray-500">Open Tills</dt>
            <dd class="text-2xl font-mono">{{ $stats['open_tills'] ?? 0 }}</dd>
        </div>
        <div class="p-4 bg-gray-50 rounded">
            <dt class="text-sm text-gray-500">Closed Tills</dt>
            <dd class="text-2xl font-mono">{{ $stats['closed_tills'] ?? 0 }}</dd>
        </div>
        <div class="p-4 bg-gray-50 rounded">
            <dt class="text-sm text-gray-500">Total Variance</dt>
            <dd class="text-2xl font-mono {{ ((float) ($stats['total_variance'] ?? 0)) != 0 ? 'text-red-600' : '' }}">
                {{ number_format((float) ($stats['total_variance'] ?? 0), 2) }}
            </dd>
        </div>
    </div>

    {{-- Currency Positions Table --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Currency Positions</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Currency</th>
                        <th>Quantity</th>
                        <th>Avg Cost</th>
                        <th>Market Value (MYR)</th>
                        <th>Unrealized P/L</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($positions as $position)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center font-bold text-xs">
                                    {{ substr($position['currency_code'], 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-medium">{{ $position['currency_code'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $position['currency_name'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="font-mono">{{ number_format((float) $position['quantity'], 2) }}</td>
                        <td class="font-mono">{{ number_format((float) $position['avg_cost'], 4) }}</td>
                        <td class="font-mono">{{ number_format((float) $position['market_value'], 2) }} MYR</td>
                        <td>
                            @php
                                $pl = (float) ($position['unrealized_pl'] ?? 0);
                                $plClass = $pl >= 0 ? 'text-green-600' : 'text-red-600';
                            @endphp
                            <span class="font-mono {{ $plClass }}">
                                {{ $pl >= 0 ? '+' : '' }}{{ number_format($pl, 2) }} MYR
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('stock-cash.position', $position['id']) }}" class="btn btn-ghost btn-sm">Details</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-gray-500">No positions found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
