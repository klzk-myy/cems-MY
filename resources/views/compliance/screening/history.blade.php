<x-app-layout title="Screening History">
    <div class="space-y-6">
        <x-page-header
            title="Screening History"
            description="Sanctions screening history for {{ $customer->full_name }}"
        />

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Screened Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Result</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Match Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Action Taken</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($history as $result)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $result->created_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $result->screened_name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge :variant="match ($result->result) {
                                    'clear' => 'success',
                                    'potential_match', 'confirmed_match' => 'danger',
                                    default => 'gray',
                                }">
                                    {{ ucfirst(str_replace('_', ' ', (string) $result->result)) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ $result->match_score !== null ? round((float) $result->match_score * 100, 1).'%' : 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $result->action_taken ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No screening history for this customer." :colspan="5" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-button variant="secondary" href="{{ url()->previous() }}">Back</x-button>
    </div>
</x-app-layout>
