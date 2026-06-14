<x-app-layout title="Counter History">
    <div class="space-y-6">
        <x-page-header title="Counter History" :actions="true">
            {{ $counter->name ?? 'Counter 1' }} - Session records and transactions

            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('counters.index') }}">Back to Counters</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid cols="3">
            <x-stat-card label="Total Sessions" :value="$stats['total_sessions'] ?? 0" />
            <x-stat-card label="Total Transactions" :value="$stats['total_transactions'] ?? 0" />
            <x-stat-card label="Total Value" :value="'RM '.number_format($stats['total_value'] ?? 0, 2)" />
        </x-stat-grid>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Operator</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Opening Float</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Closing Float</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Variance</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($sessions ?? [] as $session)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">
                                {{ $session->opened_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $session->user->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $session->type ?? 'Regular' }}</td>
                            <td class="px-4 py-3 text-sm">RM {{ number_format($session->opening_float ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($session->closing_float)
                                    RM {{ number_format($session->closing_float, 2) }}
                                @else
                                    <span class="text-ink-muted/50">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($session->variance)
                                    <span class="{{ $session->variance != 0 ? 'text-danger-text' : 'text-success-text' }}">
                                        RM {{ number_format($session->variance, 2) }}
                                    </span>
                                @else
                                    <span class="text-ink-muted/50">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($session->closed_at)
                                    <x-badge variant="gray">Closed</x-badge>
                                @else
                                    <x-badge variant="success">Open</x-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No session history found." :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="mt-4">
            {{ $sessions->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>
