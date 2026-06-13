<x-app-layout title="Cases">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Compliance Cases</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage ongoing compliance investigations</p>
                </div>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Create Case
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Priority</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="pending_review">Pending Review</option>
                    <option value="closed">Closed</option>
                </select>
                <input type="text" placeholder="Search case ID or customer..." class="w-full px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Search
                </button>
            </div>
        </div>

        <!-- Cases Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Case ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned To</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($cases as $case)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $case->case_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $case->case_type?->label() }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $case->customer?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $priorityColor = match ($case->priority?->value) {
                                        'Critical' => 'bg-red-100 text-red-700',
                                        'High' => 'bg-orange-100 text-orange-700',
                                        'Medium' => 'bg-yellow-100 text-yellow-700',
                                        'Low' => 'bg-green-100 text-green-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $priorityColor }}">
                                    {{ $case->priority?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $statusColor = match ($case->status?->value) {
                                        'Open' => 'bg-blue-100 text-blue-700',
                                        'UnderReview' => 'bg-yellow-100 text-yellow-700',
                                        'PendingApproval' => 'bg-purple-100 text-purple-700',
                                        'Closed' => 'bg-green-100 text-green-700',
                                        'Escalated' => 'bg-red-100 text-red-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $statusColor }}">
                                    {{ $case->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $case->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $case->created_at?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('compliance.cases.show', $case) }}" class="text-blue-600 hover:text-blue-800">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-sm text-center text-gray-500">No cases found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>