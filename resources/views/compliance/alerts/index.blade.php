<x-app-layout title="Compliance Alerts Index">
    <x-page-header title="Compliance Alerts Index" description="Alert triage queue" />

    @php
        $priorityVariants = [
            'critical' => 'error',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'gray',
        ];
        $statusVariants = [
            'Open' => 'warning',
            'Under_Review' => 'info',
            'Escalated' => 'error',
            'Resolved' => 'success',
            'Rejected' => 'gray',
        ];
    @endphp

    {{-- Queue summary --}}
    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Total Alerts" :value="$summary['total']" color="blue" />
        <x-stat-card label="Critical" :value="$summary['critical']" color="red" />
        <x-stat-card label="Unassigned" :value="$summary['unassigned']" color="yellow" />
        <x-stat-card label="Resolved Today" :value="$summary['resolved_today']" color="green" />
    </div>

    <x-card class="mt-4 !p-0">
        @if ($alerts->isEmpty())
            <x-empty-state title="No alerts found" description="There are no alerts awaiting triage." />
        @else
            <form method="POST" action="{{ route('compliance.cases.store') }}">
                @csrf
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">
                                <input type="checkbox" onchange="document.querySelectorAll('[data-alert-check]').forEach(c => c.checked = this.checked)" aria-label="Select all alerts">
                            </th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Priority</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Type</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Customer</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Reason</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Risk Score</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Assigned To</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($alerts as $alert)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3">
                                    @if ($alert->case_id === null && $alert->status->value !== 'Resolved')
                                        <input type="checkbox" name="alert_ids[]" value="{{ $alert->id }}" data-alert-check aria-label="Select alert {{ $alert->id }}">
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="$priorityVariants[strtolower($alert->priority->value)] ?? 'gray'">
                                        {{ $alert->priority->value }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3">{{ $alert->type->value }}</td>
                                <td class="px-4 py-3">{{ $alert->customer?->full_name ?? '—' }}</td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $alert->reason }}">{{ $alert->reason }}</td>
                                <td class="px-4 py-3">{{ $alert->risk_score }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="$statusVariants[$alert->status->value] ?? 'gray'">
                                        {{ str_replace('_', ' ', $alert->status->value) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($alert->assignedTo)
                                        {{ $alert->assignedTo->username }}
                                    @else
                                        <span class="text-ink-muted">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('compliance.alerts.show', $alert) }}" class="text-info hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <div class="flex justify-end border-t border-border px-4 py-3">
                    <x-button variant="primary" type="submit">Create Case from Selected</x-button>
                </div>
            </form>

            <div class="border-t border-border px-4 py-3">
                {{ $alerts->links() }}
            </div>
        @endif
    </x-card>
</x-app-layout>

