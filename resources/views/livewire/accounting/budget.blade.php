@extends('layouts.base')

@section('title', 'Budget vs Actual - CEMS-MY')

@section('content')
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Budget vs Actual</h1>
        <p class="text-sm text-gray-500">Monitor budget performance by account</p>
    </div>

    {{-- Period Selector --}}
    <div class="card mb-6">
        <div class="card-body flex items-center gap-4">
            <label class="text-sm text-gray-500">Period:</label>
            <input
                type="month"
                wire:model.live="periodCode"
                class="form-input w-40">
            <span class="text-sm text-gray-500">
                Showing budget data for {{ $periodCode }}
            </span>
        </div>
    </div>

    {{-- Budget vs Actual Table --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Budget vs Actual</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="text-right">Budget</th>
                        <th class="text-right">Actual</th>
                        <th class="text-right">Variance</th>
                        <th class="text-right">Variance %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report as $item)
                    <tr>
                        <td>
                            <div>
                                <span class="font-mono text-sm">{{ $item['account_code'] }}</span>
                                <span class="ml-2">{{ $item['account_name'] ?? '' }}</span>
                            </div>
                        </td>
                        <td class="font-mono text-right">{{ number_format($item['budget'] ?? 0, 2) }}</td>
                        <td class="font-mono text-right">{{ number_format($item['actual'] ?? 0, 2) }}</td>
                        <td class="font-mono text-right {{ ($item['variance'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($item['variance'] ?? 0, 2) }}
                        </td>
                        <td class="font-mono text-right {{ ($item['variance_percentage'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($item['variance_percentage'] ?? 0, 1) }}%
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">No budget data for this period</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Unbudgeted Accounts --}}
    @if(!empty($unbudgetedAccounts))
    <div class="card mt-6">
        <div class="card-header">
            <h3 class="card-title">Accounts Without Budget</h3>
        </div>
        <div class="card-body">
            <p class="text-sm text-gray-500 mb-4">The following accounts have no budget set for this period:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($unbudgetedAccounts as $account)
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-white text-sm">
                    <span class="font-mono">{{ $account['account_code'] ?? $account }}</span>
                </span>
                @endforeach
            </div>
        </div>
    </div>
    @endif
@endsection