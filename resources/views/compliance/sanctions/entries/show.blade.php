<x-app-layout title="Sanctions Entry Details">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-ink">Sanctions Entry Details</h1>
                    <p class="mt-1 text-sm text-ink-muted">Reference: {{ $sanctionEntry->reference_number ?: 'N/A' }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('compliance.sanctions.entries.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                        Back to List
                    </a>
                    <a href="{{ route('compliance.sanctions.entries.edit', $sanctionEntry) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                        Edit
                    </a>
                </div>
            </div>
        </div>

        <!-- Entry Details -->
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-ink">Entry Information</h3>
                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                    {{ ucfirst($sanctionEntry->status->value ?? $sanctionEntry->status) }}
                </span>
            </div>
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
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                        {{ strtoupper($sanctionEntry->list_source ?: ($sanctionEntry->sanctionList?->name ?? 'N/A')) }}
                    </span>
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
        </div>

        <!-- Aliases -->
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Aliases</h3>
            @if (count($sanctionEntry->aliases) > 0)
                <ul class="space-y-2">
                    @foreach ($sanctionEntry->aliases as $alias)
                        <li class="flex items-center gap-2 text-sm text-ink">
                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                            {{ $alias }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-ink-muted">No aliases recorded.</p>
            @endif
        </div>

        <!-- Address -->
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Address</h3>
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
        </div>

        <!-- Additional Information -->
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Additional Information</h3>
            <p class="text-sm text-ink-muted">{{ $sanctionEntry->details ?: 'No additional information.' }}</p>
        </div>

        <!-- Actions -->
        <div class="bg-surface border border-border rounded-xl p-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('compliance.sanctions.entries.edit', $sanctionEntry) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Edit Entry
                </a>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                    View Related Transactions
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                    Export
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-red-50 border border-red-200 text-red-700 hover:bg-red-100">
                    Deactivate
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
