<x-app-layout title="Compliance Dashboard">
    <div class="space-y-6">
        <x-page-header title="Compliance Dashboard" />

        <x-stat-grid cols="4">
            <x-stat-card label="Open Flags" :value="$stats['open'] ?? 0" color="yellow" />
            <x-stat-card label="Under Review" :value="$stats['under_review'] ?? 0" />
            <x-stat-card label="Resolved Today" :value="$stats['resolved_today'] ?? 0" color="green" />
            <x-stat-card label="High Priority" :value="$stats['high_priority'] ?? 0" color="red" />
        </x-stat-grid>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-card title="Alerts & Cases">
                <ul class="space-y-2">
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.alerts.index') }}">Alert Triage</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.unified.index') }}">Unified Alerts</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.cases.index') }}">Case Management</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.findings.index') }}">Findings</x-button></li>
                </ul>
            </x-card>
            <x-card title="Screening & Sanctions">
                <ul class="space-y-2">
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.sanctions.index') }}">Sanction Lists</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.screening.matches.index') }}">Screening Matches</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.pep-approvals.index') }}">PEP Approvals</x-button></li>
                </ul>
            </x-card>
            <x-card title="Reporting & Review">
                <ul class="space-y-2">
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.str.index') }}">STR Reports</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.edd-reviews.index') }}">EDD Reviews</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('compliance.risk-dashboard.index') }}">Risk Dashboard</x-button></li>
                </ul>
            </x-card>
        </div>

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
                            <td class="px-4 py-3 text-sm">{{ $flag->flag_type?->label() ?? $flag->flag_type }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge variant="{{ $flag->status->value === 'Open' ? 'warning' : ($flag->status->value === 'Under_Review' ? 'info' : 'success') }}">
                                    {{ $flag->status?->label() ?? $flag->status->value }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $flag->created_at?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex gap-2">
                                    @if($flag->status->canBeAssigned())
                                        <form method="POST" action="{{ route('compliance.flags.assign', $flag) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <x-button variant="ghost" size="sm" type="submit">Assign to Me</x-button>
                                        </form>
                                    @endif
                                    @if($flag->status->canBeResolved())
                                        <form method="POST" action="{{ route('compliance.flags.resolve', $flag) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <x-button variant="ghost" size="sm" type="submit">Resolve</x-button>
                                        </form>
                                    @endif
                                </div>
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
