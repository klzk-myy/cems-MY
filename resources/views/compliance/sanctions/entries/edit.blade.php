<x-app-layout title="Edit Sanctions Entry">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header title="Edit Sanctions Entry" description="Update sanctions list entry details">
            <x-slot:actions>
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card class="p-6 mt-8">
            <form method="POST" action="{{ route('compliance.sanctions.entries.update', $sanctionEntry) }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <x-select name="list_source" label="List Source" :options="['ofac' => 'OFAC SDN', 'un' => 'UN Security Council', 'eu' => 'EU Sanctions List', 'bnm' => 'BNM List', 'other' => 'Other']" selected="{{ old('list_source', $sanctionEntry->list_source) }}" />
                    <x-input name="entity_name" label="Entity Name *" value="{{ old('entity_name', $sanctionEntry->entity_name) }}" required />
                    <x-select name="entity_type" label="Entity Type *" :options="['Individual' => 'Individual', 'Organization' => 'Organization', 'Vessel' => 'Vessel', 'Aircraft' => 'Aircraft']" selected="{{ old('entity_type', $sanctionEntry->entity_type?->value ?? $sanctionEntry->entity_type) }}" required />
                    <x-input name="reference_number" label="Reference Number" value="{{ old('reference_number', $sanctionEntry->reference_number) }}" />
                    <x-input name="nationality" label="Nationality" value="{{ old('nationality', $sanctionEntry->nationality) }}" />
                    <x-input type="date" name="listing_date" label="Date Listed" value="{{ old('listing_date', $sanctionEntry->listing_date?->format('Y-m-d')) }}" />
                    <x-input name="address" label="Address" value="{{ old('address', $sanctionEntry->address) }}" />
                    <x-input name="city" label="City" value="{{ old('city', $sanctionEntry->city) }}" />
                    <x-input name="country" label="Country" value="{{ old('country', $sanctionEntry->country) }}" />
                    <x-input name="postal_code" label="Postal Code" value="{{ old('postal_code', $sanctionEntry->postal_code) }}" />
                </div>

                <x-textarea
                    name="aliases"
                    label="Aliases"
                    rows="3"
                    placeholder="Enter aliases, one per line"
                >{{ old('aliases', is_array($sanctionEntry->aliases) ? implode("\n", $sanctionEntry->aliases) : $sanctionEntry->aliases) }}</x-textarea>

                <x-textarea
                    name="details"
                    label="Additional Information"
                    rows="3"
                >{{ old('details', $sanctionEntry->details) }}</x-textarea>

                <div class="flex justify-end gap-3">
                    <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Save Changes</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
