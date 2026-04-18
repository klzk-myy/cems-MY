<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CEMS-MY</title>
    @vite(['resources/css/app.css'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Instrument+Serif&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[--content-bg] flex items-center justify-center">
    <div class="w-full max-w-md px-6">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-[--color-accent] rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                <span class="text-white font-bold text-3xl">C</span>
            </div>
            <h1 class="text-2xl font-bold text-[--color-ink]">CEMS-MY</h1>
            <p class="text-[--color-ink-muted]">Currency Exchange Management System</p>
        </div>

        {{-- Login Card --}}
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Sign in to your account</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="alert-content">
                                <p class="alert-title">Login Failed</p>
                                <p class="alert-description">{{ $errors->first() }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" placeholder="Enter your username" value="{{ old('username') }}" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" class="form-checkbox">
                            <span class="text-sm text-[--color-ink]">Remember me</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        Sign in
                    </button>
                </form>
            </div>
        </div>

        {{-- Footer --}}
        <p class="text-center text-sm text-[--color-ink-muted] mt-6">
            CEMS-MY v1.0 - Bank Negara Malaysia Compliant
        </p>
    </div>
</body>
</html>
