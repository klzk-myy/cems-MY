<x-app-layout title="Create Branch">
    <div class="space-y-6">
        <x-page-header title="Create Branch" description="Register a new branch in the system" />

        <x-card title="Branch Information" description="Enter the branch details below">
            <form method="POST" action="{{ route('branches.store') }}">
                @csrf

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-input name="code" label="Branch Code" value="{{ old('code') }}" required maxlength="20" />
                        <x-input name="name" label="Branch Name" value="{{ old('name') }}" required />

                        <x-select
                            name="type"
                            label="Type"
                            :options="$branchTypes ?? []"
                            :selected="old('type', 'branch')"
                            required
                        />

                        <x-select
                            name="parent_id"
                            label="Parent Branch"
                            :options="($parentBranches ?? collect())->pluck('name', 'id')->toArray()"
                            placeholder="None (top-level)"
                        />

                        <x-input name="phone" label="Phone" value="{{ old('phone') }}" maxlength="30" />
                        <x-input type="email" name="email" label="Email" value="{{ old('email') }}" />

                        <x-input name="address" label="Address" value="{{ old('address') }}" />
                        <x-input name="city" label="City" value="{{ old('city') }}" />
                        <x-input name="state" label="State" value="{{ old('state') }}" />
                        <x-input name="postal_code" label="Postal Code" value="{{ old('postal_code') }}" />
                        <x-input name="country" label="Country" value="{{ old('country', 'Malaysia') }}" />

                        <x-checkbox name="is_active" label="Active" :checked="old('is_active', true)" />
                        <x-checkbox name="is_main" label="Main branch (head office)" :checked="old('is_main')" />
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-border flex items-center justify-end gap-3">
                    <x-button href="{{ route('branches.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create Branch</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
