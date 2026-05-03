<x-app-layout title="Create User">
        <!-- Page Header -->
        <div class="bg-white border-b border-[#e5e5e5]">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <h1 class="text-2xl font-semibold text-gray-900">Create User</h1>
                <p class="mt-1 text-sm text-gray-500">Add a new user to the system</p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-6 py-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">User Information</h2>
                    <p class="mt-1 text-sm text-gray-500">Enter the user's details below</p>
                </div>

                <form method="POST" action="{{ route('users.store') }}" class="p-6">
                    @csrf

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
                                value="{{ old('username') }}"
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
                                value="{{ old('email') }}"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('email') border-red-500 @enderror"
                                required
                            >
                            @error('email')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('password') border-red-500 @enderror"
                                required
                            >
                            @error('password')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg"
                                required
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
                                <option value="teller" {{ old('role') === 'teller' ? 'selected' : '' }}>
                                    Teller - Can create transactions
                                </option>
                                <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>
                                    Manager - Can approve transactions and manage counters
                                </option>
                                <option value="compliance_officer" {{ old('role') === 'compliance_officer' ? 'selected' : '' }}>
                                    Compliance Officer - Can review flagged transactions and compliance reports
                                </option>
                                <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>
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
                                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
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
                                    {{ old('is_active', '1') ? 'checked' : '' }}
                                    class="w-4 h-4 text-[#0a0a0a] border-[#e5e5e5] rounded focus:ring-[#0a0a0a]"
                                >
                                <span class="ml-2 text-sm text-gray-700">Active User</span>
                            </label>
                        </div>

                        <!-- MFA Enabled -->
                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="mfa_enabled"
                                    value="1"
                                    {{ old('mfa_enabled') ? 'checked' : '' }}
                                    class="w-4 h-4 text-[#0a0a0a] border-[#e5e5e5] rounded focus:ring-[#0a0a0a]"
                                >
                                <span class="ml-2 text-sm text-gray-700">Enable MFA (Required for all roles)</span>
                            </label>
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
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</x-app-layout>