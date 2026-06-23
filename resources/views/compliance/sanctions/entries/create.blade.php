<x-app-layout title="Create Sanctions Entry">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Create Sanctions Entry"
            description="Add a new sanctions list entry"
        >
            <x-slot:actions>
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <form method="POST" action="{{ route('compliance.sanctions.entries.store') }}">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <x-select name="list_id" label="Sanctions List *" :options="$lists->pluck('name', 'id')->toArray()" required />
                    <x-input name="entity_name" label="Entity Name *" value="{{ old('entity_name') }}" required />
                    <x-select name="entity_type" label="Entity Type *" :options="['Individual' => 'Individual', 'Organization' => 'Organization', 'Vessel' => 'Vessel', 'Aircraft' => 'Aircraft']" required />
                    <x-input name="reference_number" label="Reference Number" value="{{ old('reference_number') }}" />
                    <x-input name="nationality" label="Nationality" value="{{ old('nationality') }}" />
                    <x-input type="date" name="date_of_birth" label="Date of Birth" value="{{ old('date_of_birth') }}" />
                    <x-input type="date" name="listing_date" label="Listing Date" value="{{ old('listing_date') }}" />
                </div>

                <x-textarea
                    name="aliases"
                    label="Aliases"
                    rows="3"
                    placeholder="Enter aliases, one per line"
                >{{ old('aliases') }}</x-textarea>

                <x-textarea
                    name="details"
                    label="Additional Information"
                    rows="3"
                >{{ old('details') }}</x-textarea>

                <div class="flex justify-end gap-3">
                    <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Save Entry</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
