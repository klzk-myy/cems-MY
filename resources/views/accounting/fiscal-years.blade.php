<x-app-layout title="Fiscal Years">
    <x-page-header title="Fiscal Years" description="Manage fiscal years" />

    <x-table>
        <x-slot:thead>
            <tr>
                <th class="px-4 py-3">Year Code</th>
                <th class="px-4 py-3">Start Date</th>
                <th class="px-4 py-3">End Date</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
        </x-slot:thead>
        <x-slot:tbody>
            @forelse($fiscalYears ?? [] as $fiscalYear)
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-ink">{{ $fiscalYear->year_code }}</td>
                    <td class="px-4 py-3 text-sm text-ink">{{ $fiscalYear->start_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-sm text-ink">{{ $fiscalYear->end_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3"><x-badge variant="success">{{ $fiscalYear->status ?? 'Active' }}</x-badge></td>
                    <td class="px-4 py-3">
                        <x-button variant="ghost" size="sm">View</x-button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="px-4 py-3 text-sm text-ink-muted" colspan="5">
                        <x-empty-state title="No fiscal years found" description="Create a fiscal year to get started." />
                    </td>
                </tr>
            @endforelse
        </x-slot:tbody>
    </x-table>
</x-app-layout>
