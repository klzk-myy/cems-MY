<x-app-layout title="Edit User">
    <!-- Page Header -->
    <div class="bg-white border-b border-[#e5e5e5]">
        <div class="max-w-7xl mx-auto px-6 py-6">
            <x-page-header title="Edit User" description="Update user details and permissions" />
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-6 py-6">
        <x-card title="User Information" description="Update the user's details below">
            <form method="POST" action="{{ route('users.update', $user->id) }}">
                @csrf
                @method('PUT')

                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-input name="username" label="Username" value="{{ old('username', $user->username) }}" required autofocus />
                        <x-input type="email" name="email" label="Email Address" value="{{ old('email', $user->email) }}" required />
                        <x-input
                            type="password"
                            name="password"
                            label="Password"
                            placeholder="Leave blank to keep current password"
                            help="Leave blank to keep the current password"
                        />
                        <x-input
                            type="password"
                            name="password_confirmation"
                            label="Confirm Password"
                            placeholder="Confirm new password"
                        />

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
                            selected="{{ old('role', $user->role->value) }}"
                            required
                        />

                        <x-select
                            name="branch_id"
                            label="Branch"
                            :options="($branches ?? collect())->pluck('name', 'id')->toArray()"
                            placeholder="Select a branch (optional)"
                            selected="{{ old('branch_id', $user->branch_id) }}"
                        />

                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                                    class="w-4 h-4 text-[#0a0a0a] border-[#e5e5e5] rounded focus:ring-[#0a0a0a]"
                                >
                                <span class="ml-2 text-sm text-gray-700">Active User</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">MFA Status</label>
                            <div class="mt-2">
                                @if($user->mfa_enabled)
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                                        Enabled
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-canvas-subtle text-gray-700">
                                        Disabled
                                    </span>
                                @endif
                                <p class="mt-1 text-xs text-ink-muted">MFA can be configured by the user in their profile settings</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-[#e5e5e5] pt-6">
                        <h4 class="text-sm font-medium text-ink mb-4">Account Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-xs text-ink-muted">Last Login</p>
                                <p class="mt-1 text-sm text-ink">
                                    {{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted">Created At</p>
                                <p class="mt-1 text-sm text-ink">{{ $user->created_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted">Updated At</p>
                                <p class="mt-1 text-sm text-ink">{{ $user->updated_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-[#e5e5e5] flex items-center justify-end gap-3">
                    <x-button href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Update User</x-button>
                </div>
            </form>
        </x-card>
    </main>
</x-app-layout>
