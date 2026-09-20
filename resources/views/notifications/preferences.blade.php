<x-app-layout title="Notification Preferences">
    <x-page-header title="Notification Preferences" description="Choose which in-app and email notifications you receive." />

    <form method="POST" action="{{ route('notifications.preferences.update') }}" class="space-y-4">
        @csrf

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @php
            $prefs = auth()->user()->notification_preferences ?? [];
        @endphp

        <div class="divide-y divide-border rounded border border-border bg-surface">
            @foreach ($types as $key => $label)
                <label class="flex items-center justify-between gap-4 p-3">
                    <span>{{ $label }}</span>
                    <x-checkbox name="types[{{ $key }}]" value="1" :checked="($prefs[$key] ?? true)" />
                </label>
            @endforeach
        </div>

        <label class="flex items-center justify-between gap-4 p-3">
            <span>Send me the notification email digest</span>
            <x-checkbox name="digest_enabled" value="1" :checked="($prefs['digest_enabled'] ?? true)" />
        </label>

        <p class="text-sm text-ink-muted">Unticked notification types are disabled. Compliance-critical alerts may still be delivered where regulation requires.</p>

        <x-button type="submit" variant="primary">Save Preferences</x-button>
    </form>
</x-app-layout>
