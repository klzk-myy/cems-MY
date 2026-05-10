<x-app-layout title="Compliance Alerts">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Compliance Alerts</h1>
            <p class="mt-1 text-sm text-gray-500">Monitor and manage compliance alerts</p>
        </div>

        <!-- Alerts Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            @if($alerts->isEmpty())
            <div class="p-6 text-center text-gray-500">
                No alerts found
            </div>
            @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alert ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned To</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($alerts as $alert)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $alert->id }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $alert->type ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">{{ $alert->priority ?? 'medium' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $alert->reason ?? $alert->description ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">{{ $alert->status?->value ?? 'open' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($alert->assignedTo)
                                {{ $alert->assignedTo->username }}
                            @else
                                <span class="text-gray-400">Unassigned</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('compliance.alerts.show', $alert) }}" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</x-app-layout>