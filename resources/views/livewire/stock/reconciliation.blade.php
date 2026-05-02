@extends('layouts.base')

<div>
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Till Reconciliation</h1>
            <p class="text-sm text-gray-500">End of day reconciliation report</p>
        </div>
        <a href="{{ route('stock-cash.index') }}" class="btn btn-secondary">Back</a>
    </div>

    {{-- Filters --}}
    <div class="card mb-6">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="tillId" class="block text-sm font-medium text-gray-900 mb-1">Till ID</label>
                    <input
                        type="text"
                        id="tillId"
                        wire:model.live="tillId"
                        class="input"
                        placeholder="Enter Till ID"
                    />
                </div>
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-900 mb-1">Date</label>
                    <input
                        type="date"
                        id="date"
                        wire:model.live="date"
                        class="input"
                    />
                </div>
            </div>
        </div>
    </div>

    @if($tillId && !empty($reconciliation))
        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Opening Balance</dt>
                <dd class="text-xl font-mono">RM {{ number_format((float) ($reconciliation['opening_balance'] ?? 0), 2) }}</dd>
            </div>
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Total Buys</dt>
                <dd class="text-xl font-mono">
                    {{ $reconciliation['purchases']['count'] ?? 0 }}
                    <span class="text-sm text-green-600">(+RM {{ number_format((float) ($reconciliation['purchases']['total'] ?? 0), 2) }})</span>
                </dd>
            </div>
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Total Sells</dt>
                <dd class="text-xl font-mono">
                    {{ $reconciliation['sales']['count'] ?? 0 }}
                    <span class="text-sm text-red-600">(-RM {{ number_format((float) ($reconciliation['sales']['total'] ?? 0), 2) }})</span>
                </dd>
            </div>
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Net Flow</dt>
                <dd class="text-xl font-mono">RM {{ number_format((float) ($reconciliation['net_flow'] ?? 0), 2) }}</dd>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Expected Closing</dt>
                <dd class="text-2xl font-mono">RM {{ number_format((float) ($reconciliation['expected_closing'] ?? 0), 2) }}</dd>
            </div>
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Actual Closing</dt>
                <dd class="text-2xl font-mono">
                    @if($reconciliation['actual_closing'])
                        RM {{ number_format((float) $reconciliation['actual_closing'], 2) }}
                    @else
                        <span class="text-gray-500">Not Closed</span>
                    @endif
                </dd>
            </div>
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Variance</dt>
                <dd class="text-2xl font-mono @if((float) ($reconciliation['variance'] ?? 0) != 0) text-red-600 @endif">
                    @if($reconciliation['variance'] !== null)
                        RM {{ number_format((float) $reconciliation['variance'], 2) }}
                    @else
                        -
                    @endif
                </dd>
            </div>
        </div>

        {{-- Transactions Table --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transactions</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Rate</th>
                            <th class="text-right">MYR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                        <tr>
                            <td class="font-mono">{{ $tx['created_at'] }}</td>
                            <td>{{ $tx['customer_name'] }}</td>
                            <td>
                                <span class="badge {{ $tx['type'] === 'Buy' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $tx['type'] }}
                                </span>
                            </td>
                            <td class="font-mono text-right">{{ number_format((float) $tx['amount'], 2) }} {{ $tx['currency_code'] }}</td>
                            <td class="font-mono text-right">{{ number_format((float) $tx['rate'], 4) }}</td>
                            <td class="font-mono text-right">RM {{ number_format((float) $tx['myr_value'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">No transactions</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($tillId)
        <div class="card">
            <div class="card-body text-center py-12">
                <p class="text-gray-500">No reconciliation data found for Till ID: {{ $tillId }} on {{ $date }}</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-12">
                <p class="text-gray-500">Enter a Till ID to view reconciliation data</p>
            </div>
        </div>
    @endif
</div>
