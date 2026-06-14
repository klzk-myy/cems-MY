<x-app-layout title="Compliance Alerts">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-ink">Compliance Alerts</h1>
            <p class="mt-1 text-sm text-ink-muted">Monitor and manage compliance alerts</p>
        </div>

        <!-- Alerts Table -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            @if($alerts->isEmpty())
            <div class="p-6 text-center text-ink-muted">
                No alerts found
            </div>
            @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Alert ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Severity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Assigned To</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($alerts as $alert)
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">{{ $alert->id }}</td>
                        <td class="px-4 py-3 text-sm text-ink">{{ $alert->type ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">{{ $alert->priority ?? 'medium' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $alert->reason ?? $alert->description ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">{{ $alert->status?->value ?? 'open' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($alert->assignedTo)
                                {{ $alert->assignedTo->username }}
                            @else
                                <span class="text-ink-muted/50">Unassigned</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('compliance.alerts.show', $alert) }}" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</x-app-layout>