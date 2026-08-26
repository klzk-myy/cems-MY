<x-app-layout title="Report Schedule">
    <div class="space-y-6">
        <x-page-header
            title="Report Schedule"
            description="{{ $schedule->report_type?->label() ?? $schedule->report_type?->value }} automated generation schedule"
        >
            <x-slot:actions>
                <form action="{{ route($schedule->is_active ? 'reports.schedules.pause' : 'reports.schedules.resume', $schedule) }}" method="POST">
                    @csrf
                    <x-button type="submit" variant="{{ $schedule->is_active ? 'warning' : 'success' }}">
                        {{ $schedule->is_active ? 'Pause' : 'Resume' }}
                    </x-button>
                </form>
                <x-button href="{{ route('reports.schedules.edit', $schedule) }}" variant="primary">Edit</x-button>
                <x-button href="{{ route('reports.schedules.index') }}" variant="secondary">Back to Schedules</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Report Type</dt>
                    <dd class="text-sm text-ink">{{ $schedule->report_type?->label() ?? $schedule->report_type?->value }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Status</dt>
                    <dd>
                        <x-badge variant="{{ $schedule->is_active ? 'success' : 'gray' }}">
                            {{ $schedule->is_active ? 'Active' : 'Paused' }}
                        </x-badge>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Frequency (Cron)</dt>
                    <dd class="text-sm text-ink">
                        <code>{{ $schedule->cron_expression }}</code>
                        <span class="text-ink-muted"> — {{ $schedule->getFriendlySchedule() }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Next Due</dt>
                    <dd class="text-sm text-ink">{{ $schedule->next_run_at?->format('d M Y H:i') ?? 'Not scheduled' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Last Run</dt>
                    <dd class="text-sm text-ink">{{ $schedule->last_run_at?->format('d M Y H:i') ?? 'Never' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Created By</dt>
                    <dd class="text-sm text-ink">{{ $schedule->createdBy?->username ?? 'System' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Parameters</dt>
                    <dd class="text-sm text-ink"><code>{{ json_encode($schedule->parameters) ?: 'Defaults' }}</code></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-ink-muted">Notification Recipients</dt>
                    <dd class="text-sm text-ink">
                        @if (filled($schedule->notification_recipients))
                            {{ implode(', ', $schedule->notification_recipients) }}
                        @else
                            None
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card title="Recent Runs">
            <div class="overflow-x-auto">
                <x-table>
                    <x-slot:thead>
                        <th>Status</th>
                        <th>Started At</th>
                        <th>Completed At</th>
                        <th>Rows</th>
                        <th>Error</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($recentRuns as $run)
                            <tr>
                                <td>
                                    <x-badge variant="{{ $run->status->color() === 'green' ? 'success' : ($run->status->color() === 'red' ? 'danger' : 'info') }}">
                                        {{ $run->status->label() }}
                                    </x-badge>
                                </td>
                                <td>{{ $run->started_at?->format('d M Y H:i') }}</td>
                                <td>{{ $run->completed_at?->format('d M Y H:i') }}</td>
                                <td>{{ $run->row_count }}</td>
                                <td class="max-w-md truncate text-danger-text" title="{{ $run->error_message }}">{{ $run->error_message }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-ink-muted py-4">No runs recorded yet.</td></tr>
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </div>
        </x-card>
    </div>
</x-app-layout>
