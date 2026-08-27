<x-auth-layout title="Change Password">
    <x-page-header title="Change Password" description="Update your account password" />

    <x-card>
        <form method="POST" action="{{ route('password.change.submit') }}" class="space-y-4">
            @csrf
            <x-input name="current_password" label="Current Password" type="password" :required="true" />
            <x-input name="password" label="New Password" type="password" :required="true" />
            <x-input name="password_confirmation" label="Confirm New Password" type="password" :required="true" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('dashboard') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Update Password</x-button>
            </div>
        </form>
    </x-card>
</x-auth-layout>
