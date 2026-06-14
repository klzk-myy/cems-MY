<x-app-layout title="Sanctions Import Logs">
    <div class="space-y-6">
        <x-page-header title="Sanctions Import Logs" :actions="true">
            History of sanctions list imports

            <x-slot:actions>
                <x-button variant="primary">Import List</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <x-select name="source" :options="['ofac' => 'OFAC SDN', 'un' => 'UN Security Council', 'eu' => 'EU Sanctions List', 'bnm' => 'BNM List']" placeholder="All Sources" inline />
            <x-select name="status" :options="['completed' => 'Completed', 'failed' => 'Failed', 'partial' => 'Partial']" placeholder="All Status" inline />
            <x-input name="imported_at" type="date" inline />
            <x-button variant="secondary" type="submit">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Import ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Imported At</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Records</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Added</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Updated</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Removed</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm text-ink">IMP-2024-001</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="info">OFAC</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-15 02:00:00</td>
                        <td class="px-4 py-3 text-sm text-ink">5,432</td>
                        <td class="px-4 py-3 text-sm text-success-text">12</td>
                        <td class="px-4 py-3 text-sm text-warning-text">45</td>
                        <td class="px-4 py-3 text-sm text-danger-text">3</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="success">Completed</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <x-button variant="ghost" size="sm" href="#">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm text-ink">IMP-2024-002</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="purple">UN</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-15 02:30:00</td>
                        <td class="px-4 py-3 text-sm text-ink">1,205</td>
                        <td class="px-4 py-3 text-sm text-success-text">5</td>
                        <td class="px-4 py-3 text-sm text-warning-text">12</td>
                        <td class="px-4 py-3 text-sm text-danger-text">1</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="success">Completed</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <x-button variant="ghost" size="sm" href="#">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm text-ink">IMP-2024-003</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="danger">BNM</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-14 02:00:00</td>
                        <td class="px-4 py-3 text-sm text-ink">89</td>
                        <td class="px-4 py-3 text-sm text-success-text">2</td>
                        <td class="px-4 py-3 text-sm text-warning-text">0</td>
                        <td class="px-4 py-3 text-sm text-danger-text">0</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="success">Completed</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <x-button variant="ghost" size="sm" href="#">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm text-ink">IMP-2024-004</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="info">OFAC</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-10 02:00:00</td>
                        <td class="px-4 py-3 text-sm text-ink">-</td>
                        <td class="px-4 py-3 text-sm text-ink-muted/50">-</td>
                        <td class="px-4 py-3 text-sm text-ink-muted/50">-</td>
                        <td class="px-4 py-3 text-sm text-ink-muted/50">-</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badge variant="danger">Failed</x-badge>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <x-button variant="ghost" size="sm" href="#">View Log</x-button>
                        </td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
