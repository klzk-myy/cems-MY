<x-app-layout title="Change Password">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Change Password</h1>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl max-w-2xl">
            <div class="p-6">
                @if (session('status'))
                    <div class="mb-6 rounded-lg bg-green-50 p-4 text-sm text-green-700">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                            <input type="password" name="current_password" id="current_password"
                                class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('current_password') border-red-500 @enderror"
                                required autofocus>
                            @error('current_password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" name="password" id="password"
                                class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg @error('password') border-red-500 @enderror"
                                required>
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Minimum 12 characters with uppercase, lowercase, number and special character.</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg"
                                required>
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                Update Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>