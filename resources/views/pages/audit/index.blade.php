<x-app-layout title="Audit Log">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Audit Log</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Timestamp</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Entity</th>
                        <th class="px-4 py-3">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs ?? [] as $log)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $log->created_at?->format('M d, Y H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $log->user->name ?? 'System' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs 
                                @if(str_contains($log->action, 'create')) bg-green-100 text-green-800
                                @elseif(str_contains($log->action, 'delete')) bg-red-100 text-red-800
                                @else bg-blue-100 text-blue-800 @endif">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $log->entity_type ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate">{{ json_encode($log->changes) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No audit logs found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $logs->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>