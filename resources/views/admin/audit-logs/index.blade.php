<x-app-layout title="Audit Trail">
    <x-page-header title="Audit Trail" description="Immutable hash-chained record of all system activity." />

    @if (isset($unsealedCount) && $unsealedCount > 0)
        <div class="mb-4 rounded border border-warning-border bg-warning-subtle p-3 text-sm text-warning-text">
            {{ $unsealedCount }} entries awaiting async sealing. Run <code>php artisan audit:verify</code> to verify chain integrity.
        </div>
    @endif

    <x-filter-bar method="GET">
        <x-input name="action" label="Action contains" :value="request('action')" inline />
        <x-input name="entity_type" label="Entity type" :value="request('entity_type')" inline />
        <x-select name="severity" label="Severity" :options="['' => 'All', 'INFO' => 'Info', 'WARNING' => 'Warning', 'ERROR' => 'Error', 'CRITICAL' => 'Critical']" :value="request('severity')" inline />
        <x-button type="submit" variant="secondary">Filter</x-button>
    </x-filter-bar>

    <x-card>
        <x-table>
            <x-slot:thead>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">#</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Entity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Severity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Sealed</th>
                </tr>
            </x-slot:thead>
            <x-slot:tbody>
                @forelse ($logs as $log)
                    <tr class="border-t border-border hover:bg-canvas-subtle">
                        <td class="px-4 py-3 font-mono">
                            <a href="{{ route('admin.audit-logs.show', $log) }}" class="text-primary hover:underline">{{ $log->id }}</a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->username ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->entity_type }}{{ $log->entity_id ? '#'.$log->entity_id : '' }}</td>
                        <td class="px-4 py-3">{{ $log->severity ?? 'INFO' }}</td>
                        <td class="px-4 py-3">{{ $log->entry_hash ? 'Yes' : 'Pending' }}</td>
                    </tr>
                @empty
                    <x-empty-state message="No audit entries match the filters." :colspan="7" />
                @endforelse
            </x-slot:tbody>
        </x-table>
    </x-card>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>
