<x-app-layout title="Unified Compliance View">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-ink">Unified Compliance View</h1>
            <p class="mt-1 text-sm text-ink-muted">Comprehensive overview of all compliance activities</p>
        </div>

        <form method="GET" action="{{ route('compliance.unified.index') }}" class="bg-surface border border-border rounded-xl p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
                <div>
                    <label for="source" class="block text-xs font-medium text-ink-muted uppercase mb-1">Source</label>
                    <select name="source" id="source" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="all" {{ ($request->get('source', 'all') === 'all') ? 'selected' : '' }}>All</option>
                        <option value="alert" {{ ($request->get('source') === 'alert') ? 'selected' : '' }}>Alert</option>
                        <option value="finding" {{ ($request->get('source') === 'finding') ? 'selected' : '' }}>Finding</option>
                    </select>
                </div>
                <div>
                    <label for="priority" class="block text-xs font-medium text-ink-muted uppercase mb-1">Priority</label>
                    <select name="priority" id="priority" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All</option>
                        <option value="Critical" {{ ($request->get('priority') === 'Critical') ? 'selected' : '' }}>Critical</option>
                        <option value="High" {{ ($request->get('priority') === 'High') ? 'selected' : '' }}>High</option>
                        <option value="Medium" {{ ($request->get('priority') === 'Medium') ? 'selected' : '' }}>Medium</option>
                        <option value="Low" {{ ($request->get('priority') === 'Low') ? 'selected' : '' }}>Low</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-ink-muted uppercase mb-1">Status</label>
                    <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All</option>
                        <option value="open" {{ ($request->get('status') === 'open') ? 'selected' : '' }}>Open</option>
                        <option value="in_review" {{ ($request->get('status') === 'in_review') ? 'selected' : '' }}>In Review</option>
                        <option value="resolved" {{ ($request->get('status') === 'resolved') ? 'selected' : '' }}>Resolved</option>
                        <option value="dismissed" {{ ($request->get('status') === 'dismissed') ? 'selected' : '' }}>Dismissed</option>
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-xs font-medium text-ink-muted uppercase mb-1">Type</label>
                    <select name="type" id="type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All</option>
                        <option value="Velocity_Exceeded" {{ ($request->get('type') === 'Velocity_Exceeded') ? 'selected' : '' }}>Velocity Exceeded</option>
                        <option value="Structuring_Pattern" {{ ($request->get('type') === 'Structuring_Pattern') ? 'selected' : '' }}>Structuring Pattern</option>
                        <option value="Sanction_Match" {{ ($request->get('type') === 'Sanction_Match') ? 'selected' : '' }}>Sanction Match</option>
                    </select>
                </div>
                <div>
                    <label for="customer" class="block text-xs font-medium text-ink-muted uppercase mb-1">Customer</label>
                    <input type="text" name="customer" id="customer" value="{{ $request->get('customer') }}" placeholder="Search..." class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label for="from_date" class="block text-xs font-medium text-ink-muted uppercase mb-1">From Date</label>
                    <input type="date" name="from_date" id="from_date" value="{{ $request->get('from_date') }}" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label for="to_date" class="block text-xs font-medium text-ink-muted uppercase mb-1">To Date</label>
                    <input type="date" name="to_date" id="to_date" value="{{ $request->get('to_date') }}" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                    Apply Filters
                </button>
                <a href="{{ route('compliance.unified.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300">
                    Clear
                </a>
            </div>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-surface border border-border rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-ink-muted uppercase">Total Items</p>
                        <p class="text-3xl font-bold text-ink mt-1">{{ $stats['total'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-canvas-subtle rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-surface border border-border rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-ink-muted uppercase">Critical</p>
                        <p class="text-3xl font-bold text-red-600 mt-1">{{ $stats['critical'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-surface border border-border rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-ink-muted uppercase">Pending</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-1">{{ $stats['pending'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-yellow-600 mt-1">Pending/Open</p>
            </div>

            <div class="bg-surface border border-border rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-ink-muted uppercase">Resolved Today</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['resolved_today'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl p-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Recent Activity</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Priority</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Assigned To</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($items as $item)
                        <tr class="badge-info">
                            <td class="px-4 py-3 text-sm text-ink">{{ $item['source'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item['id'] }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded
                                    @if($item['priority'] === 'Critical') bg-red-100 text-red-700
                                    @elseif($item['priority'] === 'High') bg-yellow-100 text-yellow-700
                                    @else bg-canvas-subtle text-gray-700
                                    @endif">
                                    {{ $item['priority_label'] ?? $item['priority'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $item['type_label'] ?? $item['type'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item['customer']['name'] ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded
                                    @if($item['status'] === 'Resolved' || $item['status'] === 'Case_Created') bg-green-100 text-green-700
                                    @elseif($item['status'] === 'Dismissed' || $item['status'] === 'Rejected') bg-canvas-subtle text-gray-700
                                    @else bg-blue-100 text-blue-700
                                    @endif">
                                    {{ $item['status_label'] ?? $item['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item['assigned_to'] ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item['date']->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ $item['url'] }}" class="text-blue-600 hover:text-blue-800">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-ink-muted">No items found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($pagination['last_page'] > 1)
                <div class="mt-4 flex justify-center">
                    <p class="text-sm text-ink-muted">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        (Total: {{ $pagination['total'] }} items)
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
