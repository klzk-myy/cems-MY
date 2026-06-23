<x-app-layout title="Sanctions Entry Details">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header title="Sanctions Entry Details" :actions="true">
            Reference: {{ $sanctionEntry->reference_number ?: 'N/A' }}

            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.sanctions.entries.index') }}">Back to List</x-button>
                <x-button variant="secondary" href="{{ route('compliance.sanctions.entries.edit', $sanctionEntry) }}">Edit</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <x-card-section title="Entry Information">
                <x-slot:actions>
                    <x-badge
                        :variant="match (strtolower($sanctionEntry->status->value ?? $sanctionEntry->status)) {
                            'active', 'enabled', 'verified' => 'success',
                            'inactive', 'disabled' => 'gray',
                            'pending' => 'warning',
                            'deactivated' => 'danger',
                            default => 'success',
                        }"
                    >
                        {{ ucfirst($sanctionEntry->status->value ?? $sanctionEntry->status) }}
                    </x-badge>
                </x-slot:actions>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Entity Name</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->entity_name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Entity Type</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->entity_type?->value ?? ucfirst($sanctionEntry->entity_type) }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">List Source</label>
                        <p class="text-sm text-ink">
                            <x-badge variant="info">
                                {{ strtoupper($sanctionEntry->list_source ?: ($sanctionEntry->sanctionList?->name ?? 'N/A')) }}
                            </x-badge>
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Reference Number</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->reference_number ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Nationality</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->nationality ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Date of Birth</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->date_of_birth?->format('Y-m-d') ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Date Listed</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->listing_date?->format('Y-m-d') ?? 'N/A' }}</p>
                    </div>
                </div>
            </x-card-section>
        </x-card>

        <x-card>
            <x-card-section title="Aliases">
                @if (count($sanctionEntry->aliases) > 0)
                    <ul class="space-y-2">
                        @foreach ($sanctionEntry->aliases as $alias)
                            <li class="flex items-center gap-2 text-sm text-ink">
                                <span class="w-2 h-2 bg-ink-muted rounded-full"></span>
                                {{ $alias }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-empty-state message="No aliases recorded." />
                @endif
            </x-card-section>
        </x-card>

        <x-card>
            <x-card-section title="Address">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Street</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->address ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">City</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->city ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Country</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->country ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Postal Code</label>
                        <p class="text-sm text-ink">{{ $sanctionEntry->postal_code ?: 'N/A' }}</p>
                    </div>
                </div>
            </x-card-section>
        </x-card>

        <x-card>
            <x-card-section title="Additional Information">
                <p class="text-sm text-ink-muted">{{ $sanctionEntry->details ?: 'No additional information.' }}</p>
            </x-card-section>
        </x-card>

        <x-card>
            <x-card-section title="Actions">
                <div class="flex flex-wrap gap-3">
                    <x-button variant="primary" href="{{ route('compliance.sanctions.entries.edit', $sanctionEntry) }}">Edit Entry</x-button>
                    <x-button variant="secondary">View Related Transactions</x-button>
                    <x-button variant="secondary">Export</x-button>
                    <x-button variant="danger">Deactivate</x-button>
                </div>
            </x-card-section>
        </x-card>
    </div>
</x-app-layout>
