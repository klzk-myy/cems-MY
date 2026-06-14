<x-app-layout title="Create Sanctions Entry">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header title="Create Sanctions Entry" description="Add a new sanctions list entry">
            <x-slot:actions>
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('compliance.sanctions.entries.store') }}" class="bg-surface border border-border rounded-xl p-6 mt-8">
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

            <div class="mb-6">
                <label for="aliases" class="block text-sm font-medium text-ink-muted mb-2">Aliases</label>
                <textarea id="aliases" name="aliases" rows="3" placeholder="Enter aliases, one per line" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black">{{ old('aliases') }}</textarea>
            </div>

            <div class="mb-6">
                <label for="details" class="block text-sm font-medium text-ink-muted mb-2">Additional Information</label>
                <textarea id="details" name="details" rows="3" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black">{{ old('details') }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Entry</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
