<x-auth-layout title="Login">
    <div class="flex min-h-screen items-center justify-center">
        <div class="w-full max-w-md">
            <div class="rounded-xl border border-border bg-surface p-8">
                <h1 class="text-center text-2xl font-bold text-ink">Sign in</h1>
                <p class="mt-1 text-center text-sm text-ink-muted">Welcome back to CEMS</p>

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="email" label="Email" type="email" placeholder="you@example.com" :required="true" />
                    <x-input name="password" label="Password" type="password" placeholder="Enter your password" :required="true" />

                    <div class="flex items-center justify-between">
                        <x-checkbox name="remember" label="Remember me" />
                        <a href="{{ route('password.request') }}" class="text-sm text-info hover:underline">Forgot password?</a>
                    </div>

                    <x-button type="submit" variant="primary" class="w-full">Sign in</x-button>
                </form>
            </div>
        </div>
    </div>
</x-auth-layout>
