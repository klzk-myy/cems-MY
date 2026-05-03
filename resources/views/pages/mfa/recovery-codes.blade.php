<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MFA Recovery Codes</title>
</head>
<body class="min-h-screen bg-gray-50">
    <div class="flex min-h-screen flex-col">
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-semibold text-gray-900">Recovery Codes</h1>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-8">
                    <div class="mb-6 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">MFA Enabled Successfully</h2>
                        <p class="mt-2 text-sm text-gray-600">Save these recovery codes in a safe place. You can use any of them to access your account if you lose your authenticator device.</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <div class="grid grid-cols-2 gap-4">
                            @foreach($recoveryCodes as $code)
                                <div class="font-mono text-sm text-gray-900 bg-white px-3 py-2 rounded border border-gray-200 text-center">
                                    {{ $code }}
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <svg class="w-5 h-5 text-yellow-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-yellow-800">Important Security Notice</p>
                                <p class="text-sm text-yellow-700 mt-1">Each recovery code can only be used once. Store them securely and never share them with anyone.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-center">
                        <a href="{{ route('dashboard') }}" class="px-6 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                            Continue to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>