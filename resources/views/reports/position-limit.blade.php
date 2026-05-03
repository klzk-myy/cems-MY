<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Position Limit Report</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f5f5] min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">

        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-[#0a0a0a]">Position Limit Report</h1>
            <p class="text-sm text-[#666666] mt-1">Currency Position vs Authorized Limits</p>
        </div>

        {{-- Actions Bar --}}
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <p class="text-sm text-[#666666]">Current position status as of {{ now()->format('d M Y H:i') }}</p>

                <div class="flex items-center gap-3">
                    @if($reportGenerated)
                    <button
                        onclick="window.print()"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-[#f5f5f5]"
                    >
                        Print
                    </button>
                    <form method="POST" action="{{ route('reports.position-limit.export') }}">
                        @csrf
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                        >
                            Export
                        </button>
                    </form>
                    @endif
                    <form method="GET" action="{{ route('reports.position-limit') }}">
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-[#f5f5f5]"
                        >
                            Refresh
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Report Content --}}
        @if($reportGenerated && !empty($reportData))

        {{-- Overall Status Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Total Currencies</p>
                <p class="text-2xl font-semibold text-[#0a0a0a]">{{ number_format($reportData['total_currencies'] ?? count($reportData['positions'] ?? [])) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Within Limits</p>
                <p class="text-2xl font-semibold text-green-600">{{ number_format($reportData['within_limits'] ?? 0) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Near Limits (80%+)</p>
                <p class="text-2xl font-semibold text-yellow-600">{{ number_format($reportData['near_limits'] ?? 0) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Exceeds Limits</p>
                <p class="text-2xl font-semibold text-red-600">{{ number_format($reportData['exceeds_limits'] ?? 0) }}</p>
            </div>
        </div>

        {{-- Position Limit Table --}}
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="border-b border-[#e5e5e5] pb-4 mb-6">
                <h2 class="text-lg font-semibold text-[#0a0a0a]">Currency Positions</h2>
                <p class="text-sm text-[#666666]">Current net positions against authorized limits</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e5e5e5]">
                            <th class="text-left py-3 px-4 font-medium text-[#333333]">Currency</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Net Position</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Limit</th>
                            <th class="text-center py-3 px-4 font-medium text-[#333333]">Utilization</th>
                            <th class="text-center py-3 px-4 font-medium text-[#333333]">Status</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['positions'] ?? [] as $position)
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#fafafa]">
                            <td class="py-3 px-4 text-[#0a0a0a] font-medium">{{ $position['currency'] }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($position['net_position'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($position['limit'], 2) }}</td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-20 bg-[#e5e5e5] rounded-full h-2">
                                        <div class="h-2 rounded-full {{ $position['utilization_percent'] >= 100 ? 'bg-red-500' : ($position['utilization_percent'] >= 80 ? 'bg-yellow-500' : 'bg-green-500') }}" style="width: {{ min($position['utilization_percent'], 100) }}%"></div>
                                    </div>
                                    <span class="text-xs text-[#666666]">{{ number_format($position['utilization_percent'], 1) }}%</span>
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
                            <td class="py-3 px-4 text-right text-[#333333] {{ $position['available'] < 0 ? 'text-red-600 font-medium' : '' }}">
                                {{ number_format($position['available'], 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-[#666666]">No position data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Alerts Section --}}
        @if(!empty($reportData['alerts']))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-[#0a0a0a] mb-4">Limit Alerts</h3>
            <div class="space-y-3">
                @foreach($reportData['alerts'] as $alert)
                <div class="flex items-start gap-3 p-4 rounded-lg {{ $alert['severity'] === 'critical' ? 'bg-red-50 border border-red-200' : 'bg-yellow-50 border border-yellow-200' }}">
                    <svg class="w-5 h-5 mt-0.5 {{ $alert['severity'] === 'critical' ? 'text-red-500' : 'text-yellow-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-[#333333]">{{ $alert['message'] }}</p>
                        <p class="text-xs text-[#666666] mt-1">{{ $alert['currency'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Regulatory Note --}}
        <div class="bg-gray-50 border border-[#e5e5e5] rounded-xl p-4">
            <p class="text-xs text-[#666666]">
                <strong class="text-[#333333]">Regulatory Note:</strong>
                Position limits are set in accordance with Bank Negara Malaysia requirements for money service businesses.
                Net positions should not exceed the authorized limit at any time. Positions exceeding limits require immediate remediation.
            </p>
        </div>

        @elseif($reportGenerated && empty($reportData))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-[#d1d1d1] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-medium text-[#333333] mb-2">No Position Data Available</h3>
            <p class="text-sm text-[#666666]">Unable to generate position limit report at this time.</p>
        </div>
        @else
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-[#d1d1d1] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-medium text-[#333333] mb-2">Position Limit Report</h3>
            <p class="text-sm text-[#666666]">Click Refresh to load the current position limit report.</p>
        </div>
        @endif

        {{-- Back Link --}}
        <div class="mt-6">
            <a href="{{ route('reports.index') }}" class="inline-flex items-center text-sm text-[#666666] hover:text-[#0a0a0a]">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Reports
            </a>
        </div>
    </div>
</body>
</html>