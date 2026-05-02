@extends('layouts.base')

@section('title', 'Accounting - CEMS-MY')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#171717]">Accounting</h1>
            <p class="text-sm text-[#6b6b6b] mt-1">Financial management and reporting</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
        <a href="{{ route('accounting.journal') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-5 hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <p class="font-medium text-sm text-[#171717]">Journal</p>
        </a>
        <a href="{{ route('accounting.ledger') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-5 hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-green-600/10 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                </svg>
            </div>
            <p class="font-medium text-sm text-[#171717]">Ledger</p>
        </a>
        <a href="{{ route('accounting.trial-balance') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-5 hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-amber-500/10 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                </svg>
            </div>
            <p class="font-medium text-sm text-[#171717]">Trial Balance</p>
        </a>
        <a href="{{ route('accounting.profit-loss') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-5 hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-amber-500/10 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <p class="font-medium text-sm text-[#171717]">Profit & Loss</p>
        </a>
        <a href="{{ route('accounting.balance-sheet') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-5 hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-[#0a0a0a]/10 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-[#0a0a0a]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
            <p class="font-medium text-sm text-[#171717]">Balance Sheet</p>
        </a>
        <a href="{{ route('accounting.cash-flow') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-5 hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="font-medium text-sm text-[#171717]">Cash Flow</p>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-green-600/10 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-sm text-[#6b6b6b]">Total Assets</p>
            <p class="text-2xl font-semibold text-[#171717]">{{ number_format((float) ($summary['total_assets'] ?? 0), 2) }}</p>
            <p class="text-xs text-[#6b6b6b] mt-1">MYR</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-red-600/10 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-sm text-[#6b6b6b]">Total Liabilities</p>
            <p class="text-2xl font-semibold text-[#171717]">{{ number_format((float) ($summary['total_liabilities'] ?? 0), 2) }}</p>
            <p class="text-xs text-[#6b6b6b] mt-1">MYR</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-blue-500/10 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
            <p class="text-sm text-[#6b6b6b]">Revenue (YTD)</p>
            <p class="text-2xl font-semibold text-[#171717]">{{ number_format((float) ($summary['revenue'] ?? 0), 2) }}</p>
            <p class="text-xs text-[#6b6b6b] mt-1">MYR</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-amber-500/10 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                    </svg>
                </div>
            </div>
            <p class="text-sm text-[#6b6b6b]">Expenses (YTD)</p>
            <p class="text-2xl font-semibold text-[#171717]">{{ number_format((float) ($summary['expenses'] ?? 0), 2) }}</p>
            <p class="text-xs text-[#6b6b6b] mt-1">MYR</p>
        </div>
    </div>

    <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-[#e5e5e5] flex items-center justify-between">
            <h3 class="text-lg font-semibold text-[#171717]">Recent Journal Entries</h3>
            <a href="{{ route('accounting.journal') }}" class="text-sm text-[#d4a843] hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Entry No.</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Accounts</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    @forelse($recentEntries as $entry)
                    <tr class="hover:bg-[#f7f7f8]/50">
                        <td class="px-4 py-3 text-[#171717]">{{ $entry['entry_date'] }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-[#171717]">{{ $entry['entry_number'] }}</td>
                        <td class="px-4 py-3 text-[#171717]">{{ $entry['description'] }}</td>
                        <td class="px-4 py-3 text-[#6b6b6b]">{{ $entry['lines_count'] }} accounts</td>
                        <td class="px-4 py-3 font-mono text-[#171717]">{{ number_format((float) $entry['total_debit'], 2) }} MYR</td>
                        <td class="px-4 py-3">
                            @php
                                $statusClass = match($entry['status']) {
                                    'Posted' => 'bg-green-100 text-green-700',
                                    'Pending' => 'bg-yellow-100 text-yellow-700',
                                    'Draft' => 'bg-gray-100 text-gray-700',
                                    'Reversed' => 'bg-blue-100 text-blue-700',
                                    default => 'bg-gray-100 text-gray-700'
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $statusClass }}">{{ $entry['status_label'] }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="text-center py-8">
                                <p class="text-[#6b6b6b]">No journal entries yet</p>
                                <p class="text-sm text-[#6b6b6b]">Create your first journal entry to get started</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
