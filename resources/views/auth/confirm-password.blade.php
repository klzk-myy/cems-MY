<x-auth-layout title="Confirm Password">
    <div class="flex min-h-screen items-center justify-center">
        <div class="w-full max-w-md">
            <div class="rounded-xl border border-border bg-surface p-8">
                <h1 class="text-center text-2xl font-bold text-ink">Confirm password</h1>
                <p class="mt-1 text-center text-sm text-ink-muted">This is a sensitive action. Please confirm your password to continue.</p>

                @if($errors->any())
                    <x-alert type="error" class="mt-4">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="password" label="Password" type="password" placeholder="Enter your password" :required="true" />

                    <x-button type="submit" variant="primary" class="w-full">Confirm</x-button>
                </form>
            </div>
        </div>
    </div>
</x-auth-layout>
