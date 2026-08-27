<x-app-layout title="Reset Password">
    <div class="flex min-h-screen items-center justify-center">
        <div class="w-full max-w-md">
            <div class="rounded-xl border border-border bg-surface p-8">
                <h1 class="text-center text-2xl font-bold text-ink">Reset Password</h1>
                <p class="mt-1 text-center text-sm text-ink-muted">Create a new password for your account</p>

                <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <x-input name="email" label="Email" type="email" :required="true" />
                    <x-input name="password" label="New Password" type="password" :required="true" />
                    <x-input name="password_confirmation" label="Confirm Password" type="password" :required="true" />
                    <x-button type="submit" variant="primary" class="w-full">Reset Password</x-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
