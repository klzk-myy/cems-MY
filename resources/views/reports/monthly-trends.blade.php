<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Trends - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-[#0a0a0a] text-white flex flex-col">
            @include('layouts.sidebar')
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Monthly Transaction Trends</h1>
                    <p class="text-sm text-gray-500 mt-1">Analyze transaction volumes and counts by month</p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500">{{ $year }}</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                <form method="GET" action="{{ route('reports.monthly-trends') }}" class="flex flex-wrap gap-4 items-end">
                    <div class="flex flex-col gap-2">
                        <label for="year" class="text-xs font-medium text-gray-500 uppercase tracking-wide">Year</label>
                        <select name="year" id="year" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900">
                            @foreach(range(date('Y') - 5, date('Y')) as $y)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="currency" class="text-xs font-medium text-gray-500 uppercase tracking-wide">Currency</label>
                        <select name="currency" id="currency" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900">
                            <option value="all">All Currencies</option>
                            @foreach($currencies as $curr)
                                <option value="{{ $curr }}" {{ $currency == $curr ? 'selected' : '' }}>{{ $curr }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] transition-colors">
                        Apply Filters
                    </button>
                </form>
            </div>

            <!-- Trend Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                @foreach($trends as $trend)
                    <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">{{ $trend['month'] }}</div>
                        <div class="text-2xl font-semibold text-gray-900">{{ number_format($trend['count']) }}</div>
                        <div class="text-sm text-gray-500 mt-1">
                            {{ $currency == 'all' ? 'All' : $currency }} {{ number_format($trend['volume'], 2) }}
                        </div>
                        @if($trend['change'] !== null)
                            <div class="text-xs mt-2 {{ $trend['change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $trend['change'] >= 0 ? '+' : '' }}{{ number_format($trend['change'], 1) }}% vs prev month
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Chart Placeholder -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Transaction Volume Trend</h2>
                <div class="h-64 flex items-center justify-center bg-gray-50 rounded-lg border border-dashed border-gray-300">
                    <div class="text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                        </svg>
                        <p class="text-sm">Chart visualization placeholder</p>
                        <p class="text-xs text-gray-400">Integrate with Chart.js or similar</p>
                    </div>
                </div>
            </div>

            <!-- Monthly Data Table -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Monthly Breakdown</h2>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Transactions</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Volume ({{ $currency == 'all' ? 'MYR' : $currency }})</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Avg Value</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">MoM Change</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($monthlyData as $data)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $data['month'] }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($data['count']) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($data['volume'], 2) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($data['avg_value'], 2) }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if($data['mom_change'] !== null)
                                        <span class="text-sm {{ $data['mom_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $data['mom_change'] >= 0 ? '+' : '' }}{{ number_format($data['mom_change'], 1) }}%
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No data available for the selected filters</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>