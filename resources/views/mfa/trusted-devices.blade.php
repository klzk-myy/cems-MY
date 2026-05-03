<x-app-layout title="Trusted Devices">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Trusted Devices</h1>
            <p class="text-gray-500 text-sm mt-1">Manage devices that remember your MFA verification</p>
        </div>

        <div class="max-w-2xl">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold">About Trusted Devices</h2>
                        <p class="text-sm text-gray-500">When you verify MFA on a trusted device, you won't be asked for a code on your next login.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden mb-6">
                <div class="p-4 border-b border-[#e5e5e5]">
                    <h3 class="font-semibold">Your Trusted Devices</h3>
                </div>

                <table class="w-full">
                    <thead>
                        <tr class="text-left text-sm text-gray-500 bg-gray-50">
                            <th class="px-4 py-3">Device</th>
                            <th class="px-4 py-3">Last Used</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trustedDevices ?? [] as $device)
                        <tr class="border-t">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center">
                                        @if($device->is_mobile)
                                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">{{ $device->name ?? 'Unknown Device' }}</div>
                                        <div class="text-xs text-gray-500">{{ $device->browser ?? 'Chrome on Windows' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ $device->last_used_at?->diffForHumans() ?? 'Just now' }}
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('mfa.trusted-devices.remove', $device) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                <p>No trusted devices.</p>
                                <p class="text-sm text-gray-400 mt-1">When you check "Remember this device", it will appear here.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(isset($currentSession))
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="font-semibold mb-4">Current Session</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Device</span>
                        <p class="font-medium">{{ $currentSession->user_agent ?? 'Chrome on Windows' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">IP Address</span>
                        <p class="font-medium">{{ $currentSession->ip_address ?? '192.168.1.100' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Verified At</span>
                        <p class="font-medium">{{ $currentSession->verified_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Expires</span>
                        <p class="font-medium">{{ $currentSession->expires_at?->format('d M Y, h:i A') ?? now()->addMinutes(15)->format('d M Y, h:i A') }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>