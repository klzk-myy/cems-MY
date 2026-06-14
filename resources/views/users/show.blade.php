<x-app-layout title="User: {{ $user->username }}">
    <div class="space-y-6">
        <x-page-header title="{{ $user->username }}" :actions="true">
            User profile and details

            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('users.index') }}">Back to List</x-button>
                <x-button variant="primary" href="{{ route('users.edit', $user->id) }}">Edit User</x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="User Information">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">Username</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $user->username }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">Email</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $user->email }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">Role</p>
                                <div class="mt-1">
                                    @switch($user->role->value)
                                        @case('teller')
                                            <x-badge variant="info">Teller</x-badge>
                                            @break
                                        @case('manager')
                                            <x-badge variant="purple">Manager</x-badge>
                                            @break
                                        @case('compliance_officer')
                                            <x-badge variant="warning">Compliance Officer</x-badge>
                                            @break
                                        @case('admin')
                                            <x-badge variant="danger">Administrator</x-badge>
                                            @break
                                    @endswitch
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">Branch</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $user->branch?->name ?? 'No branch assigned' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">Account Status</p>
                                <div class="mt-1">
                                    @if($user->is_active)
                                        <x-badge variant="success">Active</x-badge>
                                    @else
                                        <x-badge variant="danger">Inactive</x-badge>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">MFA</p>
                                <div class="mt-1">
                                    @if($user->mfa_enabled)
                                        <x-badge variant="success">Enabled</x-badge>
                                        @if($user->mfa_verified_at)
                                            <p class="mt-1 text-xs text-ink-muted">Verified: {{ $user->mfa_verified_at->format('Y-m-d H:i') }}</p>
                                        @endif
                                    @else
                                        <x-badge variant="gray">Disabled</x-badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </x-card>

                <x-card title="Account Activity">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">Last Login</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never logged in' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">Created</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $user->created_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">Last Updated</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $user->updated_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Role Permissions">
                    <div class="p-6">
                        <ul class="space-y-3">
                            @switch($user->role->value)
                                @case('teller')
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can create transactions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Rate override limit: +/-0.5%</span>
                                    </li>
                                    @break
                                @case('manager')
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can create transactions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can approve transactions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can manage counters</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Rate override limit: +/-2.0%</span>
                                    </li>
                                    @break
                                @case('compliance_officer')
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can review flagged transactions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can access compliance reports</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </li>
                                    @break
                                @case('admin')
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Full system access</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can manage users</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can manage system settings</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-success-text mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Unlimited rate override</span>
                                    </li>
                                    @break
                            @endswitch
                        </ul>
                    </div>
                </x-card>

                <x-card title="Role Description">
                    <div class="p-6">
                        <p class="text-sm text-ink-muted">{{ $user->role->description() }}</p>
                        <p class="mt-4 text-xs text-ink-muted">
                            Rate override limit:
                            @if($user->role->rateOverrideLimit() === null)
                                <span class="font-medium text-ink-muted">Unlimited</span>
                            @else
                                <span class="font-medium text-ink-muted">+/-{{ $user->role->rateOverrideLimit() }}%</span>
                            @endif
                        </p>
                    </div>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
