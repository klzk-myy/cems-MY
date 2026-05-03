<x-app-layout title="MFA Recovery">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Account Recovery</h1>
            <p class="text-gray-500 text-sm mt-1">Recover access to your account using recovery codes</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 max-w-lg">
            @if(isset($error))
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-sm text-red-700">{{ $error }}</p>
            </div>
            @endif

            <div class="mb-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold">Use Recovery Code</h2>
                        <p class="text-sm text-gray-500">Enter one of your saved recovery codes</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('mfa.recovery.verify') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recovery Code</label>
                    <input type="text" name="recovery_code" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg font-mono tracking-widest" placeholder="XXXX-XXXX-XXXX" required>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                </div>

                <button type="submit" class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] mb-4">
                    Verify Recovery Code
                </button>
            </form>

            <div class="text-center">
                <p class="text-sm text-gray-500 mb-2">Don't have recovery codes?</p>
                <a href="{{ route('mfa.setup') }}" class="text-blue-600 hover:underline text-sm">Set up new authentication</a>
            </div>

            @if(isset($remainingCodes) && $remainingCodes > 0)
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600">
                    <strong>{{ $remainingCodes }}</strong> recovery codes remaining. Store them safely.
                </p>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>