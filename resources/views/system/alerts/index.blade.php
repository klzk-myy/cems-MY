<x-app-layout title="System Alerts">
    <div class="space-y-6">
        <x-page-header title="System Alerts" description="Monitor and acknowledge system-generated alerts." />

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if(session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        @if(session('info'))
            <x-alert type="info">{{ session('info') }}</x-alert>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-ink-muted">Status:</span>
            <x-button
                :href="route('system.alerts.index', array_filter(['level' => request('level')]))"
                variant="{{ request('status') === null ? 'primary' : 'secondary' }}"
                size="sm"
            >All</x-button>
            <x-button
                :href="route('system.alerts.index', array_filter(['level' => request('level'), 'status' => 'unacknowledged']))"
                variant="{{ request('status') === 'unacknowledged' ? 'primary' : 'secondary' }}"
                size="sm"
            >Unacknowledged</x-button>
            <x-button
                :href="route('system.alerts.index', array_filter(['level' => request('level'), 'status' => 'acknowledged']))"
                variant="{{ request('status') === 'acknowledged' ? 'primary' : 'secondary' }}"
                size="sm"
            >Acknowledged</x-button>

            <span class="ml-4 text-sm text-ink-muted">Level:</span>
            <x-button
                :href="route('system.alerts.index', array_filter(['status' => request('status')]))"
                variant="{{ request('level') === null ? 'primary' : 'secondary' }}"
                size="sm"
            >All</x-button>
            <x-button
                :href="route('system.alerts.index', array_filter(['level' => 'critical', 'status' => request('status')]))"
                variant="{{ request('level') === 'critical' ? 'primary' : 'secondary' }}"
                size="sm"
            >Critical</x-button>
            <x-button
                :href="route('system.alerts.index', array_filter(['level' => 'warning', 'status' => request('status')]))"
                variant="{{ request('level') === 'warning' ? 'primary' : 'secondary' }}"
                size="sm"
            >Warning</x-button>
            <x-button
                :href="route('system.alerts.index', array_filter(['level' => 'info', 'status' => request('status')]))"
                variant="{{ request('level') === 'info' ? 'primary' : 'secondary' }}"
                size="sm"
            >Info</x-button>
        </div>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Message</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase"></th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($alerts as $alert)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3">
                                <x-badge variant="{{ $alert->level->value === 'critical' ? 'danger' : ($alert->level->value === 'warning' ? 'warning' : 'info') }}">
                                    {{ $alert->status_label }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink max-w-md">
                                <span class="block truncate" title="{{ $alert->message }}">{{ $alert->message }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $alert->source ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $alert->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($alert->isAcknowledged())
                                    <span class="text-ink-muted">
                                        Acknowledged by {{ $alert->acknowledgedBy?->name ?? 'System' }}
                                        @if($alert->acknowledged_at)
                                            · {{ $alert->acknowledged_at->diffForHumans() }}
                                        @endif
                                    </span>
                                @else
                                    <x-badge variant="warning">Unacknowledged</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if(! $alert->isAcknowledged())
                                    @if(auth()->user()?->isAdmin())
                                        <form method="POST" action="{{ route('system.alerts.acknowledge', $alert) }}">
                                            @csrf
                                            <x-button type="submit" variant="secondary" size="sm">Acknowledge</x-button>
                                        </form>
                                    @endif
                                @else
                                    <span class="text-sm text-ink-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No system alerts match your filters." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="mt-4">
            {{ $alerts->withQueryString()->links() }}
        </div>
    </div>
</x-app-layout>
