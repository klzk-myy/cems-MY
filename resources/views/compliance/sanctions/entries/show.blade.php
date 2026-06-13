<x-app-layout title="Sanctions Entry Details">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sanctions Entry Details</h1>
                    <p class="mt-1 text-sm text-gray-500">Reference: {{ $sanctionEntry->reference_number ?: 'N/A' }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('compliance.sanctions.entries.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                        Back to List
                    </a>
                    <a href="{{ route('compliance.sanctions.entries.edit', $sanctionEntry) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                        Edit
                    </a>
                </div>
            </div>
        </div>

        <!-- Entry Details -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Entry Information</h3>
                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                    {{ ucfirst($sanctionEntry->status->value ?? $sanctionEntry->status) }}
                </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Entity Name</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->entity_name }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Entity Type</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->entity_type?->value ?? ucfirst($sanctionEntry->entity_type) }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">List Source</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                        {{ strtoupper($sanctionEntry->list_source ?: ($sanctionEntry->sanctionList?->name ?? 'N/A')) }}
                    </span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Reference Number</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->reference_number ?: 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Nationality</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->nationality ?: 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Date of Birth</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->date_of_birth?->format('Y-m-d') ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Date Listed</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->listing_date?->format('Y-m-d') ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Aliases -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Aliases</h3>
            @if (count($sanctionEntry->aliases) > 0)
                <ul class="space-y-2">
                    @foreach ($sanctionEntry->aliases as $alias)
                        <li class="flex items-center gap-2 text-sm text-gray-900">
                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                            {{ $alias }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">No aliases recorded.</p>
            @endif
        </div>

        <!-- Address -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Address</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Street</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->address ?: 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">City</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->city ?: 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Country</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->country ?: 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Postal Code</label>
                    <p class="text-sm text-gray-900">{{ $sanctionEntry->postal_code ?: 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Additional Information</h3>
            <p class="text-sm text-gray-600">{{ $sanctionEntry->details ?: 'No additional information.' }}</p>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('compliance.sanctions.entries.edit', $sanctionEntry) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Edit Entry
                </a>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    View Related Transactions
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Export
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-red-50 border border-red-200 text-red-700 hover:bg-red-100">
                    Deactivate
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
