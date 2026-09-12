<x-app-layout title="Audit Log #{{ $log->id }}">
    <div class="space-y-6">
        <x-page-header title="Audit Log #{{ $log->id }}">
            Immutable hash-chained audit entry detail
            <x-slot:actions>
                @if(isset($prevId) && $prevId)
                    <x-button variant="secondary" href="{{ route('admin.audit-logs.show', $prevId) }}">&larr; Older ID {{ $prevId }}</x-button>
                @endif
                @if(isset($nextId) && $nextId)
                    <x-button variant="secondary" href="{{ route('admin.audit-logs.show', $nextId) }}">Newer ID {{ $nextId }} &rarr;</x-button>
                @endif
                <x-button variant="secondary" href="{{ route('admin.audit-logs.index') }}">Back to Audit Trail</x-button>
            </x-slot:actions>
        </x-page-header>

        @php
            $severityVariant = match ($log->severity) {
                'CRITICAL', 'ERROR' => 'danger',
                'WARNING' => 'warning',
                default => 'info',
            };
        @endphp

        <x-card title="Entry Details">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Severity</label>
                    <p><x-badge variant="{{ $severityVariant }}">{{ $log->severity ?? 'INFO' }}</x-badge></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Action</label>
                    <p class="font-mono text-ink">{{ $log->action }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Created At</label>
                    <p class="text-ink">{{ $log->created_at?->format('Y-m-d H:i:s') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Actor</label>
                    <p class="text-ink">{{ $log->user?->username ?? ($log->user_id ? 'user #'.$log->user_id : 'System') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Entity</label>
                    <p class="text-ink">{{ $log->entity_type ?? '-' }}{{ $log->entity_id ? '#'.$log->entity_id : '' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">IP Address</label>
                    <p class="font-mono text-ink">{{ $log->ip_address ?? '-' }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">User Agent</label>
                    <p class="text-ink break-all">{{ $log->user_agent ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Sealed</label>
                    <p><x-badge variant="{{ $log->entry_hash ? 'success' : 'warning' }}">{{ $log->entry_hash ? 'Yes' : 'Pending' }}</x-badge></p>
                </div>
            </div>

            @if($log->description)
                <div class="mt-4 pt-4 border-t border-border">
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Description</label>
                    <p class="text-sm text-ink">{{ $log->description }}</p>
                </div>
            @endif
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-card title="Old Values">
                @if($log->old_values)
                    <pre class="text-xs bg-canvas-subtle rounded-lg p-4 overflow-x-auto text-ink"><code>{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
                @else
                    <p class="text-sm text-ink-muted">No old values recorded.</p>
                @endif
            </x-card>

            <x-card title="New Values">
                @if($log->new_values)
                    <pre class="text-xs bg-canvas-subtle rounded-lg p-4 overflow-x-auto text-ink"><code>{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
                @else
                    <p class="text-sm text-ink-muted">No new values recorded.</p>
                @endif
            </x-card>
        </div>

        @if($log->previous_hash || $log->entry_hash)
            <x-card title="Chain Integrity">
                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Previous Hash</label>
                        <p class="font-mono text-xs break-all text-ink">{{ $log->previous_hash ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Entry Hash</label>
                        <p class="font-mono text-xs break-all text-ink">{{ $log->entry_hash ?? '-' }}</p>
                    </div>
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
