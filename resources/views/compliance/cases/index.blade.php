<x-app-layout title="Compliance Cases">
    <x-page-header title="Compliance Cases" description="Manage investigation cases">
        <x-slot:actions>
            <x-button variant="primary">New Case</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-table>
        <x-slot:thead>
            <tr>
                <th class="px-4 py-3">Case ID</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Subject</th>
                <th class="px-4 py-3">Assignee</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Date</th>
            </tr>
        </x-slot:thead>
        <x-slot:tbody>
            @forelse($cases ?? [] as $case)
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-ink">{{ $case->id }}</td>
                    <td class="px-4 py-3 text-sm text-ink">{{ $case->case_type?->value ?? $case->type }}</td>
                    <td class="px-4 py-3 text-sm text-ink">{{ $case->subject }}</td>
                    <td class="px-4 py-3 text-sm text-ink-muted">{{ $case->assignee?->name ?? 'Unassigned' }}</td>
                    <td class="px-4 py-3"><x-badge variant="warning">{{ $case->status }}</x-badge></td>
                    <td class="px-4 py-3 text-sm text-ink-muted">{{ $case->created_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="px-4 py-3 text-sm text-ink-muted" colspan="6">
                        <x-empty-state title="No cases found" description="Create a new case to get started." />
                    </td>
                </tr>
            @endforelse
        </x-slot:tbody>
    </x-table>
</x-app-layout>
