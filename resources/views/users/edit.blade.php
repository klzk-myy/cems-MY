<x-app-layout title="Edit User">
    <x-page-header title="Edit User" description="Update user information" />

    <x-card>
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input name="name" label="Full Name" :required="true" />
                <x-input name="email" label="Email" type="email" :required="true" />
                <x-select name="role" label="Role" :options="['admin' => 'Admin', 'manager' => 'Manager', 'teller' => 'Teller', 'compliance_officer' => 'Compliance Officer']" :required="true" />
            </div>
            <x-checkbox name="is_active" label="Active" :checked="true" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('users.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Update User</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
