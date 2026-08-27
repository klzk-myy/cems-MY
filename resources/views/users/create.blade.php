<x-app-layout title="Add User">
    <x-page-header title="Add User" description="Create a new system user" />

    <x-card>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input name="name" label="Full Name" :required="true" />
                <x-input name="email" label="Email" type="email" :required="true" />
                <x-input name="password" label="Password" type="password" :required="true" />
                <x-select name="role" label="Role" :options="['admin' => 'Admin', 'manager' => 'Manager', 'teller' => 'Teller', 'compliance_officer' => 'Compliance Officer']" :required="true" />
            </div>
            <div class="space-y-2">
                <x-checkbox name="is_active" label="Active User" :checked="true" />
                <x-checkbox name="mfa_enabled" label="Enable MFA (Required for all roles)" :checked="true" />
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('users.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Create User</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
