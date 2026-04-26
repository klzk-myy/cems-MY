<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-[#f7f7f8]">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-semibold text-[#171717]">CEMS-MY</h1>
                <p class="text-sm text-[#6b6b6b] mt-1">Currency Exchange Management System</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-8">
                <h2 class="text-xl font-semibold text-[#171717] mb-6">Sign in to your account</h2>
                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium text-[#171717] mb-1.5">Email address</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:border-[#d4a843] focus:ring-2 focus:ring-[#d4a843]/30" placeholder="you@example.com" required>
                        @error('email')
                            <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-[#171717] mb-1.5">Password</label>
                        <input type="password" id="password" name="password" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:border-[#d4a843] focus:ring-2 focus:ring-[#d4a843]/30" placeholder="Enter your password" required>
                        @error('password')
                            <p class="text-xs text-[#ef4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-[#e5e5e5]">
                            <span class="text-sm text-[#6b6b6b]">Remember me</span>
                        </label>
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Sign in</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>