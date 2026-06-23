<x-app-layout title="Findings">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Compliance Findings"
            description="Audit and compliance findings"
            :actions="true"
        >
            <x-slot:actions>
                <x-button variant="primary">Create Finding</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <x-select
                name="severity"
                :options="['' => 'All Severity', 'critical' => 'Critical', 'major' => 'Major', 'minor' => 'Minor']"
                inline
            />
            <x-select
                name="status"
                :options="['' => 'All Status', 'open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'accepted' => 'Accepted']"
                inline
            />
            <x-input name="date" type="date" inline />
            <x-button variant="secondary" type="submit">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Finding ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Severity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Due Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">FIND-2024-001</td>
                        <td class="px-4 py-3 text-sm text-ink">Incomplete CDD Documentation</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="danger">Critical</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">Documentation</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="warning">In Progress</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-25</td>
                        <td class="px-4 py-3 text-sm">
                            <x-button variant="ghost" size="sm" href="#">View</x-button>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">FIND-2024-002</td>
                        <td class="px-4 py-3 text-sm text-ink">Delayed STR Submission</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="warning">Major</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">Reporting</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="success">Resolved</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-20</td>
                        <td class="px-4 py-3 text-sm">
                            <x-button variant="ghost" size="sm" href="#">View</x-button>
                        </td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
