<x-app-layout title="Monthly Transaction Trends">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Monthly Transaction Trends</h1>
                <p class="text-sm text-ink-muted mt-1">Analyze transaction volumes and counts by month</p>
            </div>
            <span class="text-sm text-ink-muted">{{ $year }}</span>
        </div>

        {{-- Filters --}}
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.monthly-trends') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="year" class="text-xs font-medium text-ink-muted uppercase">Year</label>
                    <select name="year" id="year" class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                        @foreach(range(date('Y') - 5, date('Y')) as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="currency" class="text-xs font-medium text-ink-muted uppercase">Currency</label>
                    <select name="currency" id="currency" class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                        <option value="all">All Currencies</option>
                        @foreach($currencies as $curr)
                            <option value="{{ $curr }}" {{ $currency == $curr ? 'selected' : '' }}>{{ $curr }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Apply Filters
                </button>
            </form>
        </div>

        {{-- Trend Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            @foreach($trends as $trend)
                <div class="bg-surface border border-border rounded-xl p-4">
                    <div class="text-xs font-medium text-ink-muted uppercase mb-1">{{ $trend['month'] }}</div>
                    <div class="text-2xl font-semibold text-ink">{{ number_format($trend['count']) }}</div>
                    <div class="text-sm text-ink-muted mt-1">
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

        {{-- Monthly Data Table --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Monthly Breakdown</h2>
            </div>
            <table class="w-full">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Volume ({{ $currency == 'all' ? 'MYR' : $currency }})</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Value</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">MoM Change</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($monthlyData as $data)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-6 py-4 text-sm text-ink">{{ $data['month'] }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($data['count']) }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($data['volume'], 2) }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($data['avg_value'], 2) }}</td>
                            <td class="px-6 py-4 text-right">
                                @if($data['mom_change'] !== null)
                                    <span class="text-sm {{ $data['mom_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $data['mom_change'] >= 0 ? '+' : '' }}{{ number_format($data['mom_change'], 1) }}%
                                    </span>
                                @else
                                    <span class="text-sm text-ink-muted/50">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-ink-muted">No data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>