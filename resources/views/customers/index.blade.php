<x-app-layout title="Customers">
    <div class="space-y-6">
        <x-page-header title="Customers" description="Manage customer records">
            <x-slot:actions>
                @can('create', \App\Models\Customer::class)
                    <x-button href="{{ route('customers.create') }}" variant="primary">Add Customer</x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar method="GET">
            <x-input name="search" value="{{ request('search') }}" placeholder="Search by name or ID..." inline />
            <x-select name="risk_rating" :options="['' => 'All Risk Ratings', 'Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High']" :selected="request('risk_rating')" inline />
            <x-select name="nationality" :options="['' => 'All Nationalities', 'MY' => 'Malaysian', 'SG' => 'Singaporean', 'OTHER' => 'Other']" :selected="request('nationality')" inline />
            <x-button type="submit" variant="primary">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID Number</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Nationality</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Risk Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Transaction</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($customers ?? [] as $customer)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $customer->full_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $customer->id_type }}</td>
                            <td class="px-4 py-3 text-sm">{{ $customer->id_number_masked }}</td>
                            <td class="px-4 py-3 text-sm">{{ $customer->nationality }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="(
                                    $customer->risk_rating instanceof \App\Enums\RiskRating
                                        ? match ($customer->risk_rating) {
                                            \App\Enums\RiskRating::High, \App\Enums\RiskRating::Critical => 'danger',
                                            \App\Enums\RiskRating::Medium => 'warning',
                                            default => 'success',
                                        }
                                        : 'success'
                                )">
                                    {{ $customer->risk_rating instanceof \App\Enums\RiskRating
                                        ? $customer->risk_rating->label()
                                        : ($customer->risk_rating ?? 'Unknown') }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($customer->last_transaction_at)
                                    {{ $customer->last_transaction_at->format('d M Y') }}
                                @else
                                    <span class="text-ink-muted/50">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <x-button href="{{ route('customers.show', $customer) }}" variant="ghost" size="sm">View</x-button>
                                    @can('update', $customer)
                                        <x-button href="{{ route('customers.edit', $customer) }}" variant="ghost" size="sm">Edit</x-button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No customers found" :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="mt-4">
            {{ $customers->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>
