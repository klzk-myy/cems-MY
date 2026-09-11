<x-app-layout title="Compliance Alerts Show">
    <x-page-header title="Alert Detail" description="Compliance alert triage detail" />

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

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="flex items-center gap-3">
                <x-badge :variant="$priorityVariants[strtolower($alert->priority->value)] ?? 'gray'">
                    {{ $alert->priority->value }}
                </x-badge>
                <x-badge :variant="$statusVariants[$alert->status->value] ?? 'gray'">
                    {{ str_replace('_', ' ', $alert->status->value) }}
                </x-badge>
                <span class="text-sm text-ink-muted">Type: {{ $alert->type->value }}</span>
            </div>

            <h2 class="mt-4 text-lg font-semibold text-ink">Reason</h2>
            <p class="mt-1 text-sm text-ink">{{ $alert->reason }}</p>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-detail-row label="Risk Score">{{ $alert->risk_score }}</x-detail-row>
                <x-detail-row label="Assigned To">{{ $alert->assignedTo?->username ?? 'Unassigned' }}</x-detail-row>
                <x-detail-row label="Customer">{{ $alert->customer?->full_name ?? '—' }}</x-detail-row>
                <x-detail-row label="Source">{{ $alert->source ?? '—' }}</x-detail-row>
                @if ($alert->flaggedTransaction)
                    <x-detail-row label="Flagged Transaction">{{ $alert->flaggedTransaction->transaction?->reference ?? ('Flagged #'.$alert->flaggedTransaction->id) }}</x-detail-row>
                @endif
                @if ($alert->case)
                    <x-detail-row label="Linked Case">CASE-{{ $alert->case->id }}</x-detail-row>
                @endif
            </div>
        </x-card>

        <div class="flex flex-col gap-4">
            <x-card>
                <h3 class="text-sm font-semibold text-ink">Timeline</h3>
                <div class="mt-3 flex flex-col gap-3 text-sm">
                    <x-detail-row label="Created">{{ $alert->created_at?->format('Y-m-d H:i') }}</x-detail-row>
                    <x-detail-row label="Reviewed By">{{ $alert->reviewed_by ?? '—' }}</x-detail-row>
                    <x-detail-row label="Escalated At">{{ $alert->escalated_at?->format('Y-m-d H:i') ?? '—' }}</x-detail-row>
                    <x-detail-row label="Resolved At">{{ $alert->resolved_at?->format('Y-m-d H:i') ?? '—' }}</x-detail-row>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>

