<x-app-layout title="Position Limit Report">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Position Limit Report</h1>
                <p class="text-sm text-ink-muted mt-1">Currency Position vs Authorized Limits</p>
            </div>
            <p class="text-sm text-ink-muted">Current position as of {{ now()->format('d M Y H:i') }}</p>
        </div>

        {{-- Actions Bar --}}
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                @if($reportGenerated)
                <div class="flex gap-3">
                    <button onclick="window.print()" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                        Print
                    </button>
                    <form method="POST" action="{{ route('reports.position-limit.export') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                            Export
                        </button>
                    </form>
                </div>
                @endif
                <form method="GET" action="{{ route('reports.position-limit') }}">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                        Refresh
                    </button>
                </form>
            </div>
        </div>

        {{-- Report Content --}}
        @if($reportGenerated && !empty($reportData))
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Total Currencies</p>
                <p class="text-2xl font-semibold text-ink">{{ number_format($reportData['total_currencies'] ?? count($reportData['positions'] ?? [])) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Within Limits</p>
                <p class="text-2xl font-semibold text-green-600">{{ number_format($reportData['within_limits'] ?? 0) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Near Limits (80%+)</p>
                <p class="text-2xl font-semibold text-yellow-600">{{ number_format($reportData['near_limits'] ?? 0) }}</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-5">
                <p class="text-xs text-ink-muted mb-1">Exceeds Limits</p>
                <p class="text-2xl font-semibold text-red-600">{{ number_format($reportData['exceeds_limits'] ?? 0) }}</p>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-ink mb-4">Currency Positions</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th class="text-left py-3 px-4 font-medium text-ink-muted">Currency</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Net Position</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Limit</th>
                        <th class="text-center py-3 px-4 font-medium text-ink-muted">Utilization</th>
                        <th class="text-center py-3 px-4 font-medium text-ink-muted">Status</th>
                        <th class="text-right py-3 px-4 font-medium text-ink-muted">Available</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['positions'] ?? [] as $position)
                    <tr class="border-b border-border hover:bg-canvas-subtle">
                        <td class="py-3 px-4 text-ink font-medium">{{ $position['currency'] }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($position['net_position'], 2) }}</td>
                        <td class="py-3 px-4 text-right text-ink-muted">{{ number_format($position['limit'], 2) }}</td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-20 bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $position['utilization_percent'] >= 100 ? 'bg-red-500' : ($position['utilization_percent'] >= 80 ? 'bg-yellow-500' : 'bg-green-500') }}" style="width: {{ min($position['utilization_percent'], 100) }}%"></div>
                                </div>
                                <span class="text-xs text-ink-muted">{{ number_format($position['utilization_percent'], 1) }}%</span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($position['utilization_percent'] >= 100)
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Exceeded</span>
                            @elseif($position['utilization_percent'] >= 80)
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Near Limit</span>
                            @else
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">OK</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right text-ink-muted {{ $position['available'] < 0 ? 'text-red-600 font-medium' : '' }}">
                            {{ number_format($position['available'], 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-ink-muted">No position data available</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($reportData['alerts']))
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Limit Alerts</h3>
            <div class="space-y-3">
                @foreach($reportData['alerts'] as $alert)
                <div class="flex items-start gap-3 p-4 rounded-lg {{ $alert['severity'] === 'critical' ? 'bg-red-50 border border-red-200' : 'bg-yellow-50 border border-yellow-200' }}">
                    <svg class="w-5 h-5 mt-0.5 {{ $alert['severity'] === 'critical' ? 'text-red-500' : 'text-yellow-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-ink">{{ $alert['message'] }}</p>
                        <p class="text-xs text-ink-muted mt-1">{{ $alert['currency'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @elseif($reportGenerated && empty($reportData))
        <div class="bg-surface border border-border rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-ink mb-2">No Position Data Available</h3>
            <p class="text-sm text-ink-muted">Unable to generate position limit report at this time.</p>
        </div>
        @else
        <div class="bg-surface border border-border rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-ink mb-2">Position Limit Report</h3>
            <p class="text-sm text-ink-muted">Click Refresh to load the current position limit report.</p>
        </div>
        @endif
    </div>
</x-app-layout>