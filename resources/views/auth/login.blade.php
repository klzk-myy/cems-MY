<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - CEMS-MY</title>
    @vite(['resources/css/app.css'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Instrument+Serif&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="bg-[#f5f5f5]">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            {{-- Logo Card --}}
            <div class="bg-white rounded-xl border border-[#e5e5e5] p-8 mb-4 text-center">
                <div class="w-16 h-16 bg-[#0a0a0a] rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="text-white font-bold text-2xl">C</span>
                </div>
                <h1 class="text-2xl font-semibold text-[#0a0a0a]">CEMS-MY</h1>
                <p class="text-sm text-[#737373] mt-1">Currency Exchange Management System</p>
            </div>

            {{-- Login Form --}}
            <div class="bg-white rounded-xl border border-[#e5e5e5] p-8">
                <h2 class="text-lg font-semibold text-[#0a0a0a] mb-6">Sign in to your account</h2>

                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <ul class="text-sm text-red-600 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="username" class="block text-sm font-medium text-[#0a0a0a] mb-1.5">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="{{ old('username') }}"
                            required
                            autofocus
                            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#0a0a0a] focus:ring-1 focus:ring-[#0a0a0a]/20 @error('username') border-red-500 @enderror"
                            placeholder="Enter your username"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-[#0a0a0a] mb-1.5">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#0a0a0a] focus:ring-1 focus:ring-[#0a0a0a]/20 @error('password') border-red-500 @enderror"
                            placeholder="Enter your password"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full px-4 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] transition-colors"
                    >
                        Sign In
                    </button>
                </form>
            </div>

            <p class="text-center text-sm text-[#737373] mt-4">
                &copy; {{ date('Y') }} CEMS-MY. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>