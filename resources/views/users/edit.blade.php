<x-app-layout title="Edit User">
        <!-- Page Header -->
        <div class="bg-white border-b border-[#e5e5e5]">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <h1 class="text-2xl font-semibold text-gray-900">Edit User</h1>
                <p class="mt-1 text-sm text-gray-500">Update user details and permissions</p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-6 py-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">User Information</h2>
                    <p class="mt-1 text-sm text-gray-500">Update the user's details below</p>
                </div>

                <form method="POST" action="{{ route('users.update', $user->id) }}" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Username -->
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                Username <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="{{ old('username', $user->username) }}"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('username') border-red-500 @enderror"
                                required
                                autofocus
                            >
                            @error('username')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email', $user->email) }}"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('email') border-red-500 @enderror"
                                required
                            >
                            @error('email')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password (optional) -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                Password
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('password') border-red-500 @enderror"
                                placeholder="Leave blank to keep current password"
                            >
                            <p class="mt-1 text-xs text-gray-500">Leave blank to keep the current password</p>
                            @error('password')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password
                            </label>
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg"
                                placeholder="Confirm new password"
                            >
                        </div>

                        <!-- Role -->
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                                Role <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="role"
                                name="role"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('role') border-red-500 @enderror"
                                required
                            >
                                <option value="">Select a role</option>
                                <option value="teller" {{ old('role', $user->role->value) === 'teller' ? 'selected' : '' }}>
                                    Teller - Can create transactions
                                </option>
                                <option value="manager" {{ old('role', $user->role->value) === 'manager' ? 'selected' : '' }}>
                                    Manager - Can approve transactions and manage counters
                                </option>
                                <option value="compliance_officer" {{ old('role', $user->role->value) === 'compliance_officer' ? 'selected' : '' }}>
                                    Compliance Officer - Can review flagged transactions and compliance reports
                                </option>
                                <option value="admin" {{ old('role', $user->role->value) === 'admin' ? 'selected' : '' }}>
                                    Administrator - Full system access
                                </option>
                            </select>
                            @error('role')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Branch -->
                        <div>
                            <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Branch
                            </label>
                            <select
                                id="branch_id"
                                name="branch_id"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('branch_id') border-red-500 @enderror"
                            >
                                <option value="">Select a branch (optional)</option>
                                @foreach($branches ?? [] as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Active Status -->
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

                        <!-- MFA Status (Read-only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">MFA Status</label>
                            <div class="mt-2">
                                @if($user->mfa_enabled)
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                                        Enabled
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">
                                        Disabled
                                    </span>
                                @endif
                                <p class="mt-1 text-xs text-gray-500">MFA can be configured by the user in their profile settings</p>
                            </div>
                        </div>
                    </div>

                    <!-- User Metadata (Read-only info) -->
                    <div class="mt-8 border-t border-[#e5e5e5] pt-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-4">Account Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-xs text-gray-500">Last Login</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Created At</p>
                                <p class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Updated At</p>
                                <p class="mt-1 text-sm text-gray-900">{{ $user->updated_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-8 flex items-center justify-end gap-3">
                        <a
                            href="{{ route('users.index') }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50"
                        >
                            Cancel
                        </a>
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                        >
                            Update User
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</x-app-layout>