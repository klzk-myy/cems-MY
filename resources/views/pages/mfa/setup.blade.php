<x-app-layout title="MFA Setup">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Setup Two-Factor Authentication</h1>

        <div class="max-w-lg bg-white rounded-lg shadow p-6">
            <p class="text-gray-600 mb-4">Two-factor authentication adds an extra layer of security to your account.</p>

            @if(isset($qrCodeUrl))
            <div class="text-center mb-6">
                <img src="{{ $qrCodeUrl }}" alt="QR Code" class="mx-auto">
            </div>
            @endif

            @if(isset($secret))
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Manual Entry Key</label>
                <code class="block bg-gray-100 p-2 rounded text-sm">{{ $secret }}</code>
            </div>
            @endif

            <form method="POST" action="{{ route('mfa.setup.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
                    <input type="text" name="code" class="w-full border rounded px-3 py-2" 
                           placeholder="Enter 6-digit code" maxlength="6" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                    Verify & Enable
                </button>
            </form>
        </div>
    </div>
</x-app-layout>