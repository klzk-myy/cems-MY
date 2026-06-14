<x-app-layout title="Findings">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-ink">Compliance Findings</h1>
                    <p class="mt-1 text-sm text-ink-muted">Audit and compliance findings</p>
                </div>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Create Finding
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2 text-sm bg-surface border border-border rounded-lg">
                    <option value="">All Severity</option>
                    <option value="critical">Critical</option>
                    <option value="major">Major</option>
                    <option value="minor">Minor</option>
                </select>
                <select class="px-4 py-2 text-sm bg-surface border border-border rounded-lg">
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="accepted">Accepted</option>
                </select>
                <input type="date" class="px-4 py-2 text-sm bg-surface border border-border rounded-lg">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Filter
                </button>
            </div>
        </div>

        <!-- Findings Table -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-border">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Finding ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Severity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Due Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">FIND-2024-001</td>
                        <td class="px-4 py-3 text-sm text-ink">Incomplete CDD Documentation</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Critical</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">Documentation</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">In Progress</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-25</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">FIND-2024-002</td>
                        <td class="px-4 py-3 text-sm text-ink">Delayed STR Submission</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-orange-100 text-orange-700">Major</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">Reporting</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Resolved</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">2024-01-20</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>