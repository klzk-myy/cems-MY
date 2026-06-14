<x-app-layout title="User: {{ $user->username }}">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">{{ $user->username }}</h1>
                <p class="text-sm text-ink-muted mt-1">User profile and details</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('users.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                    Back to List
                </a>
                <a href="{{ route('users.edit', $user->id) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Edit User
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="bg-surface border border-border rounded-xl">
                    <div class="px-6 py-4 border-b border-border">
                        <h2 class="text-lg font-medium text-ink">User Information</h2>
                    </div>
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
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Teller</span>
                                            @break
                                        @case('manager')
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-700">Manager</span>
                                            @break
                                        @case('compliance_officer')
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Compliance Officer</span>
                                            @break
                                        @case('admin')
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Administrator</span>
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
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Inactive</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-ink-muted uppercase tracking-wide">MFA</p>
                                <div class="mt-1">
                                    @if($user->mfa_enabled)
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Enabled</span>
                                        @if($user->mfa_verified_at)
                                            <p class="mt-1 text-xs text-ink-muted">Verified: {{ $user->mfa_verified_at->format('Y-m-d H:i') }}</p>
                                        @endif
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-canvas-subtle text-ink-muted">Disabled</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-surface border border-border rounded-xl mt-6">
                    <div class="px-6 py-4 border-b border-border">
                        <h2 class="text-lg font-medium text-ink">Account Activity</h2>
                    </div>
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
                </div>
            </div>

            <div>
                <div class="bg-surface border border-border rounded-xl">
                    <div class="px-6 py-4 border-b border-border">
                        <h2 class="text-lg font-medium text-ink">Role Permissions</h2>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-3">
                            @switch($user->role->value)
                                @case('teller')
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can create transactions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Rate override limit: +/-0.5%</span>
                                    </li>
                                    @break
                                @case('manager')
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can create transactions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can approve transactions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can manage counters</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Rate override limit: +/-2.0%</span>
                                    </li>
                                    @break
                                @case('compliance_officer')
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can review flagged transactions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can access compliance reports</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </li>
                                    @break
                                @case('admin')
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Full system access</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can manage users</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Can manage system settings</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-sm text-ink-muted">Unlimited rate override</span>
                                    </li>
                                    @break
                            @endswitch
                        </ul>
                    </div>
                </div>

                <div class="bg-surface border border-border rounded-xl mt-6">
                    <div class="px-6 py-4 border-b border-border">
                        <h2 class="text-lg font-medium text-ink">Role Description</h2>
                    </div>
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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>