<x-app-layout title="Edit Sanctions Entry">
    <x-page-header title="Edit Sanctions Entry" description="Update sanctions list entry" />

    <x-card>
        <form method="POST" action="{{ route('compliance.sanctions.entries.update', $sanctionEntry) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <x-input name="entity_name" label="Entity Name" :value="$sanctionEntry->entity_name" :required="true" />
            <x-select name="entity_type" label="Entity Type" :options="['Individual' => 'Individual', 'Organization' => 'Organization']" :value="$sanctionEntry->entity_type" :required="true" />
            <x-input name="list_source" label="List Source" :value="$sanctionEntry->list_source" />
            <x-textarea name="aliases" label="Aliases (one per line)" :value="implode(\"\n\", $sanctionEntry->aliases ?? [])" />
            <x-textarea name="details" label="Details" :value="$sanctionEntry->details" />
            <x-input name="address" label="Address" :value="$sanctionEntry->address" />
            <x-input name="city" label="City" :value="$sanctionEntry->city" />
            <x-input name="country" label="Country" :value="$sanctionEntry->country" />
            <x-input name="postal_code" label="Postal Code" :value="$sanctionEntry->postal_code" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('compliance.sanctions.entries.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Update Entry</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
