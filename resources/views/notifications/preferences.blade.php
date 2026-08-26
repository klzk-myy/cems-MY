<x-app-layout title="Notification Preferences">
    <x-page-header title="Notification Preferences" description="Choose which in-app and email notifications you receive." />

    <form method="POST" action="{{ route('notifications.preferences.update') }}" class="max-w-xl space-y-4">
        @csrf

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @php
            $prefs = auth()->user()->notification_preferences ?? [];
        @endphp

        <div class="divide-y divide-gray-100 rounded border border-gray-200 bg-white">
            @foreach ($types as $key => $label)
                <label class="flex items-center justify-between gap-4 p-3">
                    <span>{{ $label }}</span>
                    <input type="checkbox" name="types[{{ $key }}]" value="1"
                        class="rounded border-gray-300"
                        {{ ($prefs[$key] ?? true) ? 'checked' : '' }}>
                </label>
            @endforeach
        </div>

        <label class="flex items-center justify-between gap-4 p-3">
            <span>Send me the notification email digest</span>
            <input type="checkbox" name="digest_enabled" value="1"
                class="rounded border-gray-300"
                {{ ($prefs['digest_enabled'] ?? true) ? 'checked' : '' }}>
        </label>

        <p class="text-sm text-gray-500">Unticked notification types are disabled. Compliance-critical alerts may still be delivered where regulation requires.</p>

        <x-button type="submit" variant="primary">Save Preferences</x-button>
    </form>
</x-app-layout>
