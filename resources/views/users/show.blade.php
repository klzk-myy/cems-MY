<x-app-layout title="User Details">
    <x-page-header title="User Details" description="View user information">
        <x-slot:actions>
            <a href="{{ route('users.index') }}">
                <x-button variant="secondary">Back to Users</x-button>
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h3 class="text-lg font-semibold text-ink mb-4">User Information</h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-ink-muted">Name</dt><dd class="font-medium text-ink">{{ $user->name }}</dd></div>
                <div><dt class="text-ink-muted">Username</dt><dd class="font-medium text-ink">{{ $user->username }}</dd></div>
                <div><dt class="text-ink-muted">Email</dt><dd class="font-medium text-ink">{{ $user->email }}</dd></div>
                <div><dt class="text-ink-muted">Phone</dt><dd class="font-medium text-ink">{{ $user->phone ?? '—' }}</dd></div>
                <div><dt class="text-ink-muted">Role</dt><dd class="font-medium text-ink">{{ $user->role->label() }}</dd></div>
                <div><dt class="text-ink-muted">Status</dt><dd class="font-medium text-ink">{{ $user->is_active ? 'Active' : 'Inactive' }}</dd></div>
                <div><dt class="text-ink-muted">Created</dt><dd class="font-medium text-ink">{{ $user->created_at->format('M j, Y') }}</dd></div>
                <div><dt class="text-ink-muted">Last Active</dt><dd class="font-medium text-ink">{{ $user->last_active_at ? $user->last_active_at->format('M j, Y') : '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-ink mb-4">Branch</h3>
            <p class="text-sm text-ink">{{ $user->branch ? $user->branch->name : 'Not assigned' }}</p>
        </x-card>
    </div>
</x-app-layout>
