<x-app-layout title="Screening Matches">
    <div class="space-y-6">
        <x-page-header
            title="Screening Matches"
            description="Flagged and blocked sanctions screening results awaiting disposition"
        />

        @if (! $sanctionsLoaded)
            <x-alert type="warning" title="Sanctions lists not loaded">
                Sanction entries are empty - sanctions screening is ineffective until the lists are imported.
                Run <code>php artisan sanctions:update</code> immediately, or trigger an import from the
                <a href="{{ route('compliance.sanctions.index') }}" class="underline font-medium">sanctions lists screen</a>.
            </x-alert>
        @endif

        <x-filter-bar method="GET">
            <x-select
                name="status"
                :options="['flag' => 'Flagged', 'block' => 'Blocked', 'all' => 'All']"
                :selected="request('status', 'all')"
                placeholder=""
                inline
            />
            <input type="hidden" name="customer_id" value="{{ request('customer_id') }}">
            <x-button variant="primary" type="submit">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Screened</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Score</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Matched Entry</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">List</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($results as $result)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">
                                @if ($result->customer)
                                    <a href="{{ route('customers.show', $result->customer) }}" class="text-primary hover:underline">
                                        {{ $result->customer->full_name }}
                                    </a>
                                    <div class="text-xs text-ink-muted">#{{ $result->customer->id }}</div>
                                @else
                                    <span class="text-ink-muted">{{ $result->screened_name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $result->created_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ round((float) $result->match_score * 100, 1) }}%
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">
                                {{ $result->adverseMediaEntry?->name ?? $result->sanctionEntry?->entity_name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($result->source === 'adverse_media')
                                    <x-badge variant="warning">Adverse Media</x-badge>
                                @elseif ($result->sanctionEntry?->sanctionList)
                                    <x-badge variant="info">{{ $result->sanctionEntry->sanctionList->list_type->value }}</x-badge>
                                @else
                                    <span class="text-ink-muted">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge :variant="$result->result === 'block' ? 'danger' : 'warning'">
                                    {{ ucfirst($result->result) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <x-button variant="ghost" size="sm" href="{{ route('compliance.screening.matches.show', $result->id) }}">
                                    Review
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No pending screening matches." :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        {{ $results->links() }}
    </div>
</x-app-layout>
