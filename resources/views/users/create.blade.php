<x-app-layout title="Create User">
    <!-- Page Header -->
    <div class="bg-surface border-b border-border">
        <div class="max-w-7xl mx-auto px-6 py-6">
            <x-page-header title="Create User" description="Add a new user to the system" />
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-6 py-6">
        <x-card title="User Information" description="Enter the user's details below">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf

                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-input name="username" label="Username" value="{{ old('username') }}" required autofocus />
                        <x-input type="email" name="email" label="Email Address" value="{{ old('email') }}" required />
                        <x-input type="password" name="password" label="Password" required />
                        <x-input type="password" name="password_confirmation" label="Confirm Password" required />

                        <x-select
                            name="role"
                            label="Role"
                            :options="[
                                'teller' => 'Teller - Can create transactions',
                                'manager' => 'Manager - Can approve transactions and manage counters',
                                'compliance_officer' => 'Compliance Officer - Can review flagged transactions and compliance reports',
                                'admin' => 'Administrator - Full system access',
                            ]"
                            placeholder="Select a role"
                            required
                        />

                        <x-select
                            name="branch_id"
                            label="Branch"
                            :options="($branches ?? collect())->pluck('name', 'id')->toArray()"
                            placeholder="Select a branch (optional)"
                        />

                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    {{ old('is_active', '1') ? 'checked' : '' }}
                                    class="w-4 h-4 text-[#0a0a0a] border-border rounded focus:ring-[#0a0a0a]"
                                >
                                <span class="ml-2 text-sm text-gray-700">Active User</span>
                            </label>
                        </div>

                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="mfa_enabled"
                                    value="1"
                                    {{ old('mfa_enabled') ? 'checked' : '' }}
                                    class="w-4 h-4 text-[#0a0a0a] border-border rounded focus:ring-[#0a0a0a]"
                                >
                                <span class="ml-2 text-sm text-gray-700">Enable MFA (Required for all roles)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-border flex items-center justify-end gap-3">
                    <x-button href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create User</x-button>
                </div>
            </form>
        </x-card>
    </main>
</x-app-layout>
