<x-app-layout title="Edit Sanctions Entry">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header title="Edit Sanctions Entry" description="Update sanctions list entry details">
            <x-slot:actions>
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('compliance.sanctions.entries.update', $sanctionEntry) }}" class="bg-surface border border-border rounded-xl p-6 mt-8">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <x-select name="list_source" label="List Source" :options="['ofac' => 'OFAC SDN', 'un' => 'UN Security Council', 'eu' => 'EU Sanctions List', 'bnm' => 'BNM List', 'other' => 'Other']" selected="{{ old('list_source', $sanctionEntry->list_source) }}" />
                <x-input name="entity_name" label="Entity Name *" value="{{ old('entity_name', $sanctionEntry->entity_name) }}" required />
                <x-select name="entity_type" label="Entity Type *" :options="['Individual' => 'Individual', 'Organization' => 'Organization', 'Vessel' => 'Vessel', 'Aircraft' => 'Aircraft']" selected="{{ old('entity_type', $sanctionEntry->entity_type?->value ?? $sanctionEntry->entity_type) }}" required />
                <x-input name="reference_number" label="Reference Number" value="{{ old('reference_number', $sanctionEntry->reference_number) }}" />
                <x-input name="nationality" label="Nationality" value="{{ old('nationality', $sanctionEntry->nationality) }}" />
                <x-input type="date" name="date_listed" label="Date Listed" value="{{ old('date_listed', $sanctionEntry->listing_date?->format('Y-m-d')) }}" />
                <x-input name="address" label="Address" value="{{ old('address', $sanctionEntry->address) }}" />
                <x-input name="city" label="City" value="{{ old('city', $sanctionEntry->city) }}" />
                <x-input name="country" label="Country" value="{{ old('country', $sanctionEntry->country) }}" />
                <x-input name="postal_code" label="Postal Code" value="{{ old('postal_code', $sanctionEntry->postal_code) }}" />
            </div>

            <div class="mb-6">
                <label for="aliases" class="block text-sm font-medium text-gray-700 mb-2">Aliases</label>
                <textarea id="aliases" name="aliases" rows="3" placeholder="Enter aliases, one per line" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black">{{ old('aliases', is_array($sanctionEntry->aliases) ? implode("\n", $sanctionEntry->aliases) : $sanctionEntry->aliases) }}</textarea>
            </div>

            <div class="mb-6">
                <label for="details" class="block text-sm font-medium text-gray-700 mb-2">Additional Information</label>
                <textarea id="details" name="details" rows="3" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black">{{ old('details', $sanctionEntry->details) }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Changes</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
