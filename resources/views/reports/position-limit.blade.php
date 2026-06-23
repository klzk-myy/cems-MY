<x-app-layout title="Position Limit Report">
    <div class="p-6 space-y-6">
        <x-page-header title="Position Limit Report" :actions="true">
            Currency Position vs Authorized Limits

            <x-slot:actions>
                <p class="text-sm text-ink-muted">Current position as of {{ now()->format('d M Y H:i') }}</p>
            </x-slot:actions>
        </x-page-header>

        {{-- Actions Bar --}}
        <x-card>
            <div class="p-6 flex flex-wrap gap-4 items-center justify-between">
                @if($reportGenerated)
                    <div class="flex gap-3">
                        <x-button variant="secondary" onclick="window.print()">Print</x-button>
                        <form method="POST" action="{{ route('reports.position-limit.export') }}">
                            @csrf
                            <x-button variant="primary" type="submit">Export</x-button>
                        </form>
                    </div>
                @endif
                <form method="GET" action="{{ route('reports.position-limit') }}">
                    <x-button variant="secondary" type="submit">Refresh</x-button>
                </form>
            </div>
        </x-card>

        {{-- Report Content --}}
        @if($reportGenerated && !empty($reportData))
            <x-stat-grid cols="4">
                <x-stat-card label="Total Currencies" :value="number_format($reportData['total_currencies'] ?? count($reportData['positions'] ?? []))" />
                <x-stat-card label="Within Limits" :value="number_format($reportData['within_limits'] ?? 0)" color="green" />
                <x-stat-card label="Near Limits (80%+)" :value="number_format($reportData['near_limits'] ?? 0)" color="yellow" />
                <x-stat-card label="Exceeds Limits" :value="number_format($reportData['exceeds_limits'] ?? 0)" color="red" />
            </x-stat-grid>

            <x-card title="Currency Positions">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Net Position</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Limit</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Utilization</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Available</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($reportData['positions'] ?? [] as $position)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink font-medium">{{ $position['currency'] }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($position['net_position'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($position['limit'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <x-progress-bar :value="$position['utilization_percent']" />
                                        <span class="text-xs text-ink-muted">{{ number_format($position['utilization_percent'], 1) }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    @if($position['utilization_percent'] >= 100)
                                        <x-badge variant="danger">Exceeded</x-badge>
                                    @elseif($position['utilization_percent'] >= 80)
                                        <x-badge variant="warning">Near Limit</x-badge>
                                    @else
                                        <x-badge variant="success">OK</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted {{ $position['available'] < 0 ? 'text-danger-text font-medium' : '' }}">
                                    {{ number_format($position['available'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No position data available" :colspan="6" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>

            @if(!empty($reportData['alerts']))
                <x-card title="Limit Alerts">
                    <div class="p-6 space-y-3">
                        @foreach($reportData['alerts'] as $alert)
                            <x-alert
                                :type="$alert['severity'] === 'critical' ? 'error' : 'warning'"
                                :title="$alert['message']"
                                class="!mb-0"
                            >
                                <p class="text-xs text-ink-muted">{{ $alert['currency'] }}</p>
                            </x-alert>
                        @endforeach
                    </div>
                </x-card>
            @endif
        @elseif($reportGenerated && empty($reportData))
            <x-empty-state title="No Position Data Available" message="Unable to generate position limit report at this time." />
        @else
            <x-empty-state title="Position Limit Report" message="Click Refresh to load the current position limit report." />
        @endif
    </div>
</x-app-layout>
