<x-app-layout title="Create Counter">
    <div class="space-y-6">
        <x-page-header title="Create Counter" description="Register a new counter at a trading branch" />

        <x-card title="Counter Information" description="Enter the counter details below">
            <form method="POST" action="{{ route('counters.store') }}">
                @csrf

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-input name="code" label="Counter Code" value="{{ old('code') }}" required maxlength="10" />
                        <x-input name="name" label="Counter Name" value="{{ old('name') }}" required />

                        <x-select
                            name="branch_id"
                            label="Branch"
                            :options="$branches->toArray()"
                            :selected="old('branch_id')"
                            placeholder="Select branch"
                            required
                        />

                        <x-select
                            name="status"
                            label="Status"
                            :options="['active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance']"
                            :selected="old('status', 'active')"
                            required
                        />
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-border flex items-center justify-end gap-3">
                    <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create Counter</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
