<x-app-layout title="{{ ucfirst($view) }} Sanctions Entry">
    <x-page-header title="{{ ucfirst($view) }} Sanctions Entry" description="Sanctions list management" />
    <x-card>
        <form method="POST" class="space-y-4">
            @csrf
            <x-input name="name" label="Name" :required="true" />
            <x-input name="alias" label="Alias" />
            <x-select name="list_type" label="List Type" :options="['sdn' => 'SDN', 'consolidated' => 'Consolidated']" :required="true" />
            <x-textarea name="notes" label="Notes" />
            <div class="flex justify-end gap-3">
                <x-button type="submit" variant="primary">Save</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
