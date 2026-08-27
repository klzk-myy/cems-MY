<x-app-layout title="Forgot Password">
    <div class="flex min-h-screen items-center justify-center">
        <div class="w-full max-w-md">
            <div class="rounded-xl border border-border bg-surface p-8">
                <h1 class="text-center text-2xl font-bold text-ink">Forgot Password</h1>
                <p class="mt-1 text-center text-sm text-ink-muted">Enter your email to receive a reset link</p>

                <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="email" label="Email" type="email" placeholder="you@example.com" :required="true" />
                    <x-button type="submit" variant="primary" class="w-full">Send Reset Link</x-button>
                </form>

                <p class="mt-4 text-center text-sm text-ink-muted">
                    Remember your password? <a href="{{ route('login') }}" class="text-info hover:underline">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
