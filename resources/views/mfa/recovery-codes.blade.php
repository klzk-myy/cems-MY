<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery Codes - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto flex items-center justify-center">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-8 max-w-md w-full">
                <h1 class="text-2xl font-semibold text-[#171717] mb-2">Recovery Codes</h1>
                <p class="text-sm text-[#6b6b6b] mb-4">Save these codes somewhere safe. Each code can only be used once.</p>
                <div class="bg-[#f7f7f8] rounded-lg p-4 mb-6">
                    <ul class="space-y-2 text-sm font-mono">
                        @forelse($recoveryCodes ?? [] as $code)
                        <li class="text-[#171717]">{{ $code }}</li>
                        @empty
                        <li class="text-[#6b6b6b]">No recovery codes available</li>
                        @endforelse
                    </ul>
                </div>
                <a href="{{ route('dashboard') }}" class="block w-full px-4 py-2.5 text-sm font-medium text-center text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">I've Saved My Codes</a>
            </div>
        </main>
    </div>
</body>
</html>