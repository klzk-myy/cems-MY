<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">CTO Monitoring</h1>

        <div class="grid grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Active CTOs</p>
                <p class="text-3xl font-bold mt-1">{{ $activeCtos }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Expiring Soon</p>
                <p class="text-3xl font-bold mt-1 text-yellow-600">{{ $expiringSoon }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Expired</p>
                <p class="text-3xl font-bold mt-1 text-red-600">{{ $expired }}</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Customer</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">CTO Type</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Start Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">End Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ctos as $cto)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $cto->customer_name }}</td>
                        <td class="px-4 py-3">{{ $cto->type }}</td>
                        <td class="px-4 py-3">{{ $cto->start_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $cto->end_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            @if($cto->isExpired())
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">Expired</span>
                            @elseif($cto->isExpiringSoon())
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Expiring Soon</span>
                            @else
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <button wire:click="view({{ $cto->id }})" class="text-blue-600 hover:underline">View</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center">No CTOs found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>