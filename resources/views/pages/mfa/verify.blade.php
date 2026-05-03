<x-app-layout title="Verify MFA">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Two-Factor Verification</h1>

        <div class="max-w-lg bg-white rounded-lg shadow p-6">
            <p class="text-gray-600 mb-4">Enter the 6-digit code from your authenticator app.</p>

            <form method="POST" action="{{ route('mfa.verify.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
                    <input type="text" name="code" class="w-full border rounded px-3 py-2" 
                           placeholder="Enter 6-digit code" maxlength="6" required autofocus>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                    Verify
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="{{ route('mfa.recovery') }}" class="text-blue-600 hover:underline">Use Recovery Code</a>
            </div>
        </div>
    </div>
</x-app-layout>