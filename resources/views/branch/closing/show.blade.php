<x-app-layout title="Branch Closure - {{ $branch->name }}">
    <div class="space-y-6">
        <x-page-header title="Branch Closure: {{ $branch->name }}">
            <x-slot:actions>
                @if($workflow)
                    <x-badge variant="warning">{{ $workflow->status->label() }}</x-badge>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if(session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        <x-card title="Day Reconciliation — {{ $recon['date'] }}">
            <div class="space-y-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-ink-muted">Sessions</p>
                        <p class="text-sm font-medium text-ink">
                            {{ $recon['summary']['closed_counters'] + $recon['summary']['handed_over_counters'] }} closed / {{ $recon['summary']['active_counters'] }} open
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Expected Closing (MYR)</p>
                        <p class="text-sm font-medium text-ink">{{ number_format((float) $recon['totals']['closing_expected'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Counted Closing (MYR)</p>
                        <p class="text-sm font-medium text-ink">{{ number_format((float) $recon['totals']['closing_actual'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Variance (MYR)</p>
                        <p class="text-sm font-medium {{ (float) $recon['totals']['variance'] !== 0.0 ? 'text-danger-text' : 'text-success-text' }}">
                            {{ number_format((float) $recon['totals']['variance'], 2) }}
                        </p>
                    </div>
                </div>

                @if(count($recon['counters']) > 0)
                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Counter</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Opening</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Expected</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Counted</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Variance</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @foreach($recon['counters'] as $counter)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-ink">{{ $counter['counter_code'] }}</td>
                                    <td class="px-4 py-2 text-sm text-ink-muted">{{ $counter['session_status'] }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-mono text-ink">{{ number_format((float) $counter['opening_float'], 2) }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-mono text-ink">{{ number_format((float) $counter['closing_float_expected'], 2) }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-mono text-ink">{{ $counter['closing_float_actual'] !== null ? number_format((float) $counter['closing_float_actual'], 2) : '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-mono {{ (float) $counter['variance'] !== 0.0 ? 'text-danger-text' : 'text-ink' }}">{{ number_format((float) $counter['variance'], 2) }}</td>
                                </tr>
                            @endforeach
                        </x-slot:tbody>
                    </x-table>
                @else
                    <p class="text-sm text-ink-muted">No counter sessions recorded for this date.</p>
                @endif

                @if($recon['flagged_transactions'] > 0 || $recon['large_transactions'] > 0)
                    <p class="text-sm text-ink-muted">
                        {{ $recon['large_transactions'] }} large transaction(s), {{ $recon['flagged_transactions'] }} unresolved flag(s) today.
                    </p>
                @endif
            </div>
        </x-card>

        @if($workflow)
            <x-card title="Workflow Progress">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-ink-muted">Initiated</p>
                        <p class="text-sm font-medium text-ink">{{ $workflow->created_at?->format('Y-m-d H:i') ?? '-' }}</p>
                    </div>
                    @if($workflow->settled_at)
                    <div>
                        <p class="text-sm text-ink-muted">Settled</p>
                        <p class="text-sm font-medium text-ink">{{ $workflow->settled_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                    @if($workflow->finalized_at)
                    <div>
                        <p class="text-sm text-ink-muted">Finalized</p>
                        <p class="text-sm font-medium text-ink">{{ $workflow->finalized_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                </div>
            </x-card>

            <x-card title="Checklist">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-canvas-subtle rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['counters_closed'])
                                <x-icon name="check" class="w-5 h-5 text-success-text" />
                            @else
                                <x-icon name="x" class="w-5 h-5 text-danger-text" />
                            @endif
                            <span class="text-sm font-medium text-ink">Counters Closed</span>
                        </div>
                        <span class="text-sm {{ $checklist['counters_closed'] ? 'text-success-text' : 'text-danger-text' }}">
                            {{ $checklist['counters_closed'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-canvas-subtle rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['allocations_returned'])
                                <x-icon name="check" class="w-5 h-5 text-success-text" />
                            @else
                                <x-icon name="x" class="w-5 h-5 text-danger-text" />
                            @endif
                            <span class="text-sm font-medium text-ink">Teller Allocations Returned</span>
                        </div>
                        <span class="text-sm {{ $checklist['allocations_returned'] ? 'text-success-text' : 'text-danger-text' }}">
                            {{ $checklist['allocations_returned'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-canvas-subtle rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['documents_finalized'])
                                <x-icon name="check" class="w-5 h-5 text-success-text" />
                            @else
                                <x-icon name="x" class="w-5 h-5 text-danger-text" />
                            @endif
                            <span class="text-sm font-medium text-ink">Documents Finalized</span>
                        </div>
                        <span class="text-sm {{ $checklist['documents_finalized'] ? 'text-success-text' : 'text-danger-text' }}">
                            {{ $checklist['documents_finalized'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-between">
                @if($workflow->isInitiated())
                    <form method="POST" action="{{ route('branches.closing.settle', $branch) }}">
                        @csrf
                        <x-button variant="primary" type="submit">Mark as Settled</x-button>
                    </form>
                @endif

                @if($canFinalize)
                    <form method="POST" action="{{ route('branches.closing.finalize', $branch) }}">
                        @csrf
                        <x-button variant="primary" type="submit">Finalize Closure</x-button>
                    </form>
                @else
                    <p class="text-sm text-ink-muted">Complete all checklist items to finalize.</p>
                @endif
            </div>
        @else
            <x-empty-state message="No active closure workflow for this branch.">
                <x-slot:actions>
                    <form method="POST" action="{{ route('branches.closing.initiate', $branch) }}" class="inline">
                        @csrf
                        <x-button variant="primary" type="submit">Initiate Closure Workflow</x-button>
                    </form>
                </x-slot:actions>
            </x-empty-state>
        @endif
    </div>
</x-app-layout>
