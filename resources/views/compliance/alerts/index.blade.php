<x-app-layout title="Compliance Alerts">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Compliance Alerts"
            description="Monitor and manage compliance alerts"
        />

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Alert ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Severity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Assigned To</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($alerts as $alert)
                        <tr>
                            <td class="px-4 py-3 text-sm text-ink">{{ $alert->id }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $alert->type ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge
                                    :variant="match (strtolower($alert->priority?->value ?? 'medium')) {
                                        'critical' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'warning',
                                        'low' => 'info',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $alert->priority?->value ?? 'medium' }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $alert->reason ?? $alert->description ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge
                                    :variant="match (strtolower($alert->status?->value ?? 'open')) {
                                        'open' => 'warning',
                                        'in_review' => 'info',
                                        'resolved' => 'success',
                                        'dismissed' => 'gray',
                                        default => 'info',
                                    }"
                                >
                                    {{ $alert->status?->value ?? 'open' }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($alert->assignedTo)
                                    {{ $alert->assignedTo->username }}
                                @else
                                    <span class="text-ink-muted/50">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <x-button variant="ghost" size="sm" href="{{ route('compliance.alerts.show', $alert) }}">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No alerts found" :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
