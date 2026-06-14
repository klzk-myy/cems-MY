<x-app-layout title="Trusted Devices">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Trusted Devices"
            description="Manage devices that remember your MFA verification"
        />

        <div class="max-w-2xl">
            <x-card class="mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold">About Trusted Devices</h2>
                        <p class="text-sm text-ink-muted">When you verify MFA on a trusted device, you won't be asked for a code on your next login.</p>
                    </div>
                </div>
            </x-card>

            <x-card title="Your Trusted Devices" class="mb-6">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Device</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Used</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($trustedDevices ?? [] as $device)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded bg-canvas-subtle flex items-center justify-center">
                                            @if($device->is_mobile)
                                                <svg class="w-4 h-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium">{{ $device->name ?? 'Unknown Device' }}</div>
                                            <div class="text-xs text-ink-muted">{{ $device->browser ?? 'Chrome on Windows' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-ink-muted">
                                    {{ $device->last_used_at?->diffForHumans() ?? 'Just now' }}
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('mfa.trusted-devices.remove', $device) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="danger" size="sm" type="submit">Remove</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No trusted devices" :colspan="3" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>

            @if(isset($currentSession))
                <x-card title="Current Session">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-ink-muted">Device</span>
                            <p class="font-medium">{{ $currentSession->user_agent ?? 'Chrome on Windows' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">IP Address</span>
                            <p class="font-medium">{{ $currentSession->ip_address ?? '192.168.1.100' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">Verified At</span>
                            <p class="font-medium">{{ $currentSession->verified_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
                        </div>
                        <div>
                            <span class="text-ink-muted">Expires</span>
                            <p class="font-medium">{{ $currentSession->expires_at?->format('d M Y, h:i A') ?? now()->addMinutes(15)->format('d M Y, h:i A') }}</p>
                        </div>
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
