<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Trusted Devices</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-600 mb-4">These devices will not require two-factor authentication when you log in.</p>

            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Device</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Added</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Last Used</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <span class="text-2xl mr-2">{{ $device->device_icon }}</span>
                                <span>{{ $device->device_name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $device->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $device->last_used_at ? $device->last_used_at->format('Y-m-d') : 'Never' }}</td>
                        <td class="px-4 py-3">
                            <button wire:click="remove({{ $device->id }})" wire:confirm="Remove this device?" class="text-red-600 hover:underline">Remove</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-center">No trusted devices</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4 flex justify-end">
                <button wire:click="addCurrent" class="px-4 py-2 border border-[var(--color-border)] rounded">Add Current Device</button>
            </div>
        </div>
    </div>
</div>