<x-app-layout title="Position Limit Report">
    <div class="space-y-6">
        <x-page-header title="Position Limit Report">
            Currency Position vs Authorized Limits

            <x-slot:actions>
                <p class="text-sm text-ink-muted">Current position as of {{ now()->format('d M Y H:i') }}</p>
            </x-slot:actions>
        </x-page-header>

        {{-- Actions Bar --}}
        <x-card>
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-3">
                    <x-button variant="secondary" data-print>Print</x-button>
                    <form method="POST" action="{{ route('reports.position-limit.export') }}">
                        @csrf
                        <x-button variant="primary" type="submit">Export</x-button>
                    </form>
                </div>
                <form method="GET" action="{{ route('reports.position-limit') }}">
                    <x-button variant="secondary" type="submit">Refresh</x-button>
                </form>
            </div>
            <p class="mt-3 text-xs text-ink-muted">
                {{ $reportGenerated ? 'A report was generated for today.' : 'Live preview — no generated report recorded for today yet.' }}
            </p>
        </x-card>

        {{-- Report Content --}}
        @if(!empty($reportData['positions']))
            <x-stat-grid cols="4">
                <x-stat-card label="Total Currencies" :value="number_format($reportData['summary']['total_currencies'] ?? count($reportData['positions']))" />
                <x-stat-card label="Normal" :value="number_format(collect($reportData['positions'])->where('status', 'Normal')->count())" color="green" />
                <x-stat-card label="Warning (75%+)" :value="number_format($reportData['summary']['currencies_at_warning'] ?? 0)" color="yellow" />
                <x-stat-card label="Critical (90%+)" :value="number_format($reportData['summary']['currencies_at_critical'] ?? 0)" color="red" />
            </x-stat-grid>

            <x-card title="Currency Positions">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Limit</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Utilization</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Exposure (MYR)</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($reportData['positions'] as $position)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink font-medium">{{ $position['currency_code'] }} <span class="text-ink-muted font-normal">{{ $position['currency_name'] }}</span></td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $position['current_quantity'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ $position['position_limit'] !== null ? number_format((float) $position['position_limit'], 2) : '—' }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <x-progress-bar :value="(float) $position['utilization_percent']" />
                                        <span class="text-xs text-ink-muted">{{ number_format((float) $position['utilization_percent'], 1) }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <x-badge :variant="$position['status'] === 'Critical' ? 'danger' : ($position['status'] === 'Warning' ? 'warning' : 'success')">
                                        {{ $position['status'] }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $position['exposure_myr'], 2) }}</td>
                            </tr>
                        @empty
                            <x-empty-state message="No position data available" :colspan="6" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>

            <x-card title="Total Exposure">
                <p class="text-2xl font-semibold text-ink">MYR {{ number_format((float) ($reportData['total_exposure_myr'] ?? 0), 2) }}</p>
            </x-card>
        @else
            <x-empty-state title="No Position Data" message="No currency positions exist yet." />
        @endif
    </div>
</x-app-layout>
