<x-app-layout title="Register">
    <div class="flex min-h-screen items-center justify-center">
        <div class="w-full max-w-md">
            <div class="rounded-xl border border-border bg-surface p-8">
                <h1 class="text-center text-2xl font-bold text-ink">Create account</h1>
                <p class="mt-1 text-center text-sm text-ink-muted">Get started with CEMS</p>

                <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="name" label="Full Name" placeholder="John Doe" :required="true" />
                    <x-input name="email" label="Email" type="email" placeholder="you@example.com" :required="true" />
                    <x-input name="password" label="Password" type="password" placeholder="Create a password" :required="true" />
                    <x-input name="password_confirmation" label="Confirm Password" type="password" placeholder="Confirm your password" :required="true" />

                    <x-button type="submit" variant="primary" class="w-full">Create account</x-button>
                </form>

                <p class="mt-4 text-center text-sm text-ink-muted">
                    Already have an account? <a href="{{ route('login') }}" class="text-info hover:underline">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
