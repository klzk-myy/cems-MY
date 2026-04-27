<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFA Setup - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto flex items-center justify-center">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-8 max-w-md w-full">
                <h1 class="text-2xl font-semibold text-[#171717] mb-2">Set Up Authenticator App</h1>
                <p class="text-sm text-[#6b6b6b] mb-6">Scan this QR code with your authenticator app</p>
                <div class="bg-[#f7f7f8] rounded-lg p-4 mb-6 text-center">
                    <p class="font-mono text-xs break-all">{{ $secret ?? 'N/A' }}</p>
                </div>
                <form method="POST" action="{{ route('mfa.setup') }}">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-[#171717] mb-2">Verification Code</label>
                        <input type="text" name="code" placeholder="000000" maxlength="6" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Verify & Enable</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>