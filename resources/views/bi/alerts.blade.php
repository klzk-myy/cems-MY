<x-app-layout title="BI Alert Rules">
    <div class="space-y-6">
        <x-page-header
            title="Alert Rules"
            description="Threshold checks evaluated nightly against the analytics rollups — breaches raise a system alert"
        />

        @include('bi.partials.nav')

        @if($canManage)
            <x-card title="New Rule">
                <form method="POST" action="{{ route('bi.alerts.store') }}" class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    @csrf
                    <x-select name="metric" label="Metric" :options="$metrics" :selected="old('metric')" required />
                    <x-select name="comparison" label="Condition" :options="$comparisons" :selected="old('comparison')" required />
                    <x-input name="threshold" type="number" step="any" label="Threshold" :value="old('threshold')" required />
                    @if($canSeeAllBranches)
                        <x-select name="branch_id" label="Branch" :options="$branches->pluck('name', 'id')" :selected="old('branch_id')" placeholder="All branches" />
                    @endif
                    <x-select name="level" label="Level" :options="['warning' => 'Warning', 'critical' => 'Critical']" :selected="old('level', 'warning')" required />
                    <x-textarea name="notification_recipients" label="Recipients (optional — defaults to system recipients)" rows="2" placeholder="one@example.com, two@example.com">{{ old('notification_recipients') }}</x-textarea>
                    <div class="md:col-span-3 flex justify-end">
                        <x-button variant="primary" type="submit">Create Rule</x-button>
                    </div>
                </form>
            </x-card>
        @endif

        <x-card title="Configured Rules">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Metric</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Condition</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Branch</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Level</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Evaluated</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Triggered</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Active</th>
                    @if($canManage)
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Actions</th>
                    @endif
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $metrics[$rule->metric] ?? $rule->metric }}</td>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">{{ $rule->comparison->label() }} {{ $rule->threshold }}</td>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">{{ $rule->branch?->name ?? 'All branches' }}</td>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">{{ $rule->level->name }}</td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $rule->last_evaluated_at?->toDateTimeString() ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $rule->last_triggered_at?->toDateTimeString() ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">{{ $rule->is_active ? 'Yes' : 'No' }}</td>
                        @if($canManage)
                            <td class="px-4 py-3 text-sm text-right">
                                <form method="POST" action="{{ route('bi.alerts.destroy', $rule) }}" onsubmit="return confirm('Delete this alert rule?')">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="danger" size="sm" type="submit">Delete</x-button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 8 : 7 }}" class="px-4 py-6 text-sm text-center text-ink-muted">No alert rules configured.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>

            <div class="mt-4">{{ $rules->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
