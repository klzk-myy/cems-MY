<x-app-layout title="Compliance Dashboard">
    <div class="space-y-6">
        <x-page-header title="Compliance Dashboard" />

        <x-stat-grid cols="4">
            <x-stat-card label="Open Flags" :value="$stats['open'] ?? 0" color="yellow" />
            <x-stat-card label="Under Review" :value="$stats['under_review'] ?? 0" />
            <x-stat-card label="Resolved Today" :value="$stats['resolved_today'] ?? 0" color="green" />
            <x-stat-card label="High Priority" :value="$stats['high_priority'] ?? 0" color="red" />
        </x-stat-grid>

        <x-card title="Flagged Transactions">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Transaction</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Flag Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Created</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($flags ?? [] as $flag)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">{{ $flag->transaction_id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $flag->flag_type }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge variant="{{ $flag->status === 'Open' ? 'warning' : ($flag->status === 'Under_Review' ? 'info' : 'success') }}">
                                    {{ $flag->status }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $flag->created_at?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <form method="POST" action="{{ route('compliance.flags.assign', $flag) }}" class="inline">
                                    @csrf
                                    <x-button variant="ghost" size="sm" type="submit">Assign to Me</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No flagged transactions." :colspan="5" />
                    @endforelse
                </x-slot:tbody>
            </x-table>

            {{ $flags->withQueryString()->links() ?? '' }}
        </x-card>
    </div>
</x-app-layout>
