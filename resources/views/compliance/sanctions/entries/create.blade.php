<x-app-layout title="Create Sanctions Entry">
    <x-page-header title="Create Sanctions Entry" description="Add a new sanctions list entry" />

    <x-card>
        <form method="POST" action="{{ route('compliance.sanctions.entries.store') }}" class="space-y-4">
            @csrf
            <x-input name="entity_name" label="Entity Name" :required="true" />
            <x-select name="entity_type" label="Entity Type" :options="['Individual' => 'Individual', 'Organization' => 'Organization']" :required="true" />
            <x-input name="list_source" label="List Source" />
            <x-textarea name="aliases" label="Aliases (one per line)" />
            <x-textarea name="details" label="Details" />
            <x-input name="address" label="Address" />
            <x-input name="city" label="City" />
            <x-input name="country" label="Country" />
            <x-input name="postal_code" label="Postal Code" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('compliance.sanctions.entries.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Save Entry</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
