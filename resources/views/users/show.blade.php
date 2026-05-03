<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - CEMS</title>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Page Header -->
        <div class="bg-white border-b border-[#e5e5e5]">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">{{ $user->username }}</h1>
                        <p class="mt-1 text-sm text-gray-500">User profile and details</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a
                            href="{{ route('users.index') }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50"
                        >
                            Back to List
                        </a>
                        <a
                            href="{{ route('users.edit', $user->id) }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                        >
                            Edit User
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - User Info Card -->
                <div class="lg:col-span-2">
                    <div class="bg-white border border-[#e5e5e5] rounded-xl">
                        <div class="px-6 py-4 border-b border-[#e5e5e5]">
                            <h2 class="text-lg font-medium text-gray-900">User Information</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Username -->
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Username</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->username }}</p>
                                </div>

                                <!-- Email -->
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Email</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $user->email }}</p>
                                </div>

                                <!-- Role -->
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Role</p>
                                    <div class="mt-1">
                                        @switch($user->role->value)
                                            @case('teller')
                                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                                                    Teller
                                                </span>
                                                @break
                                            @case('manager')
                                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-700">
                                                    Manager
                                                </span>
                                                @break
                                            @case('compliance_officer')
                                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">
                                                    Compliance Officer
                                                </span>
                                                @break
                                            @case('admin')
                                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">
                                                    Administrator
                                                </span>
                                                @break
                                        @endswitch
                                    </div>
                                </div>

                                <!-- Branch -->
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Branch</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">
                                        {{ $user->branch?->name ?? 'No branch assigned' }}
                                    </p>
                                </div>

                                <!-- Status -->
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Account Status</p>
                                    <div class="mt-1">
                                        @if($user->is_active)
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">
                                                Inactive
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- MFA Status -->
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">MFA</p>
                                    <div class="mt-1">
                                        @if($user->mfa_enabled)
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                                                Enabled
                                            </span>
                                            @if($user->mfa_verified_at)
                                                <p class="mt-1 text-xs text-gray-500">
                                                    Verified: {{ $user->mfa_verified_at->format('Y-m-d H:i') }}
                                                </p>
                                            @endif
                                        @else
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">
                                                Disabled
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Timestamps -->
                    <div class="bg-white border border-[#e5e5e5] rounded-xl mt-6">
                        <div class="px-6 py-4 border-b border-[#e5e5e5]">
                            <h2 class="text-lg font-medium text-gray-900">Account Activity</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Last Login</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">
                                        {{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never logged in' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Created</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">
                                        {{ $user->created_at->format('Y-m-d H:i:s') }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Last Updated</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">
                                        {{ $user->updated_at->format('Y-m-d H:i:s') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Permissions & Role Info -->
                <div class="lg:col-span-1">
                    <!-- Role Permissions Card -->
                    <div class="bg-white border border-[#e5e5e5] rounded-xl">
                        <div class="px-6 py-4 border-b border-[#e5e5e5]">
                            <h2 class="text-lg font-medium text-gray-900">Role Permissions</h2>
                        </div>
                        <div class="p-6">
                            <ul class="space-y-3">
                                @switch($user->role->value)
                                    @case('teller')
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can create transactions</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Rate override limit: +/-0.5%</span>
                                        </li>
                                        @break
                                    @case('manager')
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can create transactions</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can approve transactions</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can manage counters</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Rate override limit: +/-2.0%</span>
                                        </li>
                                        @break
                                    @case('compliance_officer')
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can review flagged transactions</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can access compliance reports</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can submit CTOS reports</span>
                                        </li>
                                        @break
                                    @case('admin')
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Full system access</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can manage users</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Can manage system settings</span>
                                        </li>
                                        <li class="flex items-start">
                                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span class="text-sm text-gray-700">Unlimited rate override</span>
                                        </li>
                                        @break
                                @endswitch
                            </ul>
                        </div>
                    </div>

                    <!-- Role Description -->
                    <div class="bg-white border border-[#e5e5e5] rounded-xl mt-6">
                        <div class="px-6 py-4 border-b border-[#e5e5e5]">
                            <h2 class="text-lg font-medium text-gray-900">Role Description</h2>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-gray-600">{{ $user->role->description() }}</p>
                            <p class="mt-4 text-xs text-gray-500">
                                Rate override limit:
                                @if($user->role->rateOverrideLimit() === null)
                                    <span class="font-medium text-gray-700">Unlimited</span>
                                @else
                                    <span class="font-medium text-gray-700">+/-{{ $user->role->rateOverrideLimit() }}%</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>