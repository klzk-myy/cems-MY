<x-app-layout title="Sanctions Import Logs">
    <div class="space-y-6">
        <x-page-header title="Sanctions Import Logs" description="History of sanctions list imports">
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.sanctions.index') }}">
                    Manage Sanction Lists
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        <x-filter-bar method="GET">
            <x-select
                name="source"
                :options="$sources->all()"
                placeholder="All Sources"
                :selected="request('source')"
                inline
            />
            <x-select
                name="status"
                :options="['success' => 'Success', 'partial' => 'Partial', 'failed' => 'Failed']"
                placeholder="All Status"
                :selected="request('status')"
                inline
            />
            <x-button variant="primary" type="submit">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <div class="overflow-x-auto">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Imported At</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Added</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Updated</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Deactivated</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Error</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse ($logs as $log)
                            @php
                                $statusVariant = match ($log['status']) {
                                    'success' => 'success',
                                    'partial' => 'warning',
                                    default => 'danger',
                                };
                            @endphp
                            <tr class="border-t border-border hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm">
                                    @isset($log['list'])
                                        <span class="text-ink font-medium">{{ $log['list']['name'] }}</span>
                                    @else
                                        <span class="text-ink-muted">Unknown source</span>
                                    @endisset
                                    @isset($log['triggered_by'])
                                        <div class="text-xs text-ink-muted">by user #{{ $log['triggered_by'] }}</div>
                                    @endisset
                                </td>
                                <td class="px-4 py-3 text-sm text-ink-muted whitespace-nowrap">
                                    {{ \Illuminate\Support\Carbon::parse($log['imported_at'])->format('d M Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-success-text tabular-nums">{{ number_format($log['records_added']) }}</td>
                                <td class="px-4 py-3 text-sm text-warning-text tabular-nums">{{ number_format($log['records_updated']) }}</td>
                                <td class="px-4 py-3 text-sm text-danger-text tabular-nums">{{ number_format($log['records_deactivated']) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <x-badge :variant="$statusVariant">{{ ucfirst($log['status']) }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-ink-muted max-w-xs truncate" title="{{ $log['error_message'] ?? '' }}">
                                    {{ $log['error_message'] ? \Illuminate\Support\Str::limit($log['error_message'], 80) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No import logs recorded yet." :colspan="7" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </div>
        </x-card>
    </div>
</x-app-layout>
