<x-app-layout title="Report Schedules">
    <div class="space-y-6">
        <x-page-header title="Report Schedules" description="Manage automated report generation schedules">
            <x-slot:actions>
                <x-button href="{{ route('reports.schedules.create') }}" variant="primary">Create Schedule</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <form method="GET" class="flex flex-wrap gap-3 mb-4 items-end">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Report Type</label>
                    <select name="type" class="border border-border rounded px-3 py-2 text-sm bg-canvas">
                        <option value="">All Types</option>
                        @foreach ($reportTypes as $type)
                            <option value="{{ $type['value'] }}" {{ request('type') === $type['value'] ? 'selected' : '' }}>{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Status</label>
                    <select name="status" class="border border-border rounded px-3 py-2 text-sm bg-canvas">
                        <option value="">All</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <x-button type="submit" variant="secondary">Filter</x-button>
            </form>

            <div class="overflow-x-auto">
                <x-table>
                    <x-slot:thead>
                        <th>Report Type</th>
                        <th>Cadence (Cron)</th>
                        <th>Next Run</th>
                        <th>Last Run</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($schedules as $schedule)
                            <tr>
                                <td>{{ $schedule->report_type->value ?? $schedule->report_type }}</td>
                                <td><code class="text-xs">{{ $schedule->cron_expression }}</code></td>
                                <td>{{ $schedule->next_run_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td>{{ $schedule->last_run_at?->format('d M Y H:i') ?? 'Never' }}</td>
                                <td>
                                    <x-badge variant="{{ $schedule->is_active ? 'success' : 'gray' }}">
                                        {{ $schedule->is_active ? 'Active' : 'Paused' }}
                                    </x-badge>
                                </td>
                                <td class="flex gap-2">
                                    <x-button href="{{ route('reports.schedules.show', $schedule) }}" variant="secondary">View</x-button>
                                    <x-button href="{{ route('reports.schedules.edit', $schedule) }}" variant="info">Edit</x-button>
                                    @if ($schedule->is_active)
                                        <form action="{{ route('reports.schedules.pause', $schedule) }}" method="POST">
                                            @csrf
                                            <x-button type="submit" variant="warning">Pause</x-button>
                                        </form>
                                    @else
                                        <form action="{{ route('reports.schedules.resume', $schedule) }}" method="POST">
                                            @csrf
                                            <x-button type="submit" variant="success">Resume</x-button>
                                        </form>
                                    @endif
                                    <form action="{{ route('reports.schedules.destroy', $schedule) }}" method="POST" onsubmit="return confirm('Delete this schedule?')">
                                        @csrf @method('DELETE')
                                        <x-button type="submit" variant="danger">Delete</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-ink-muted py-4">No schedules found.</td></tr>
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </div>

            <div class="mt-4">{{ $schedules->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
