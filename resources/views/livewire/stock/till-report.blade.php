@extends('layouts.base')

<div>
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Till Report</h1>
            <p class="text-sm text-gray-500">Till balance and status report</p>
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

    @if($tillId && !empty($balances))
        {{-- Summary --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Till ID</dt>
                <dd class="text-xl font-mono">{{ $tillId }}</dd>
            </div>
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Date</dt>
                <dd class="text-xl font-mono">{{ $date }}</dd>
            </div>
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Currencies</dt>
                <dd class="text-xl font-mono">{{ count($balances) }}</dd>
            </div>
            <div class="p-4 bg-gray-50 rounded">
                <dt class="text-sm text-gray-500">Status</dt>
                <dd class="text-xl font-mono">
                    @if(!empty($balances) && ($balances[0]['closed_at'] ?? null))
                        <span class="badge badge-success">Closed</span>
                    @else
                        <span class="badge badge-warning">Open</span>
                    @endif
                </dd>
            </div>
        </div>

        {{-- Till Balances Table --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Till Balances</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Till ID</th>
                            <th>Currency</th>
                            <th>Opened By</th>
                            <th class="text-right">Opening</th>
                            <th>Closed By</th>
                            <th class="text-right">Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($balances as $balance)
                        <tr>
                            <td class="font-mono">{{ $balance['till_id'] ?? 'N/A' }}</td>
                            <td class="font-mono">{{ $balance['currency']['code'] ?? 'N/A' }}</td>
                            <td>{{ $balance['opener']['name'] ?? 'N/A' }}</td>
                            <td class="font-mono text-right">RM {{ number_format((float) ($balance['opening_balance'] ?? 0), 2) }}</td>
                            <td>
                                @if($balance['closer'])
                                    {{ $balance['closer']['name'] }}
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="font-mono text-right">
                                @if($balance['closing_balance'])
                                    RM {{ number_format((float) $balance['closing_balance'], 2) }}
                                @else
                                    <span class="text-gray-500">Open</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">No data found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($tillId)
        <div class="card">
            <div class="card-body text-center py-12">
                <p class="text-gray-500">No till report data found for Till ID: {{ $tillId }} on {{ $date }}</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-12">
                <p class="text-gray-500">Enter a Till ID to view the till report</p>
            </div>
        </div>
    @endif
</div>
