<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <main class="flex-1 bg-[--color-canvas-subtle] p-8 overflow-y-auto flex items-center justify-center">
            <div class="bg-white border border-[--color-border] rounded-xl p-8 max-w-md w-full">
                <h1 class="text-2xl font-semibold text-[--color-ink] mb-2">Account Recovery</h1>
                <p class="text-sm text-[--color-ink-muted] mb-6">Use a recovery code to access your account</p>
                <form method="POST" action="{{ route('mfa.recovery') }}">
                    @csrf
                    <div class="mb-4">
                        <input type="text" name="code" placeholder="Recovery Code" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Use Recovery Code</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>