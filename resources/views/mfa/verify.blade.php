<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify MFA - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <main class="flex-1 bg-[--color-canvas-subtle] p-8 overflow-y-auto flex items-center justify-center">
            <div class="bg-white border border-[--color-border] rounded-xl p-8 max-w-md w-full">
                <h1 class="text-2xl font-semibold text-[--color-ink] mb-2">Two-Factor Authentication</h1>
                <p class="text-sm text-[--color-ink-muted] mb-6">Enter the 6-digit code from your authenticator app</p>
                <form method="POST" action="{{ route('mfa.verify') }}">
                    @csrf
                    <div class="mb-4">
                        <input type="text" name="code" placeholder="000000" maxlength="6" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg text-center text-2xl tracking-widest" required autofocus>
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Verify</button>
                </form>
                @if(session('error'))
                <p class="text-red-600 text-sm mt-4">{{ session('error') }}</p>
                @endif
            </div>
        </main>
    </div>
</body>
</html>