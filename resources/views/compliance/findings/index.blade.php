<x-app-layout title="Findings">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Compliance Findings</h1>
                    <p class="mt-1 text-sm text-gray-500">Audit and compliance findings</p>
                </div>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Create Finding
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Severity</option>
                    <option value="critical">Critical</option>
                    <option value="major">Major</option>
                    <option value="minor">Minor</option>
                </select>
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="accepted">Accepted</option>
                </select>
                <input type="date" class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Filter
                </button>
            </div>
        </div>

        <!-- Findings Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Finding ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">FIND-2024-001</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Incomplete CDD Documentation</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Critical</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">Documentation</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">In Progress</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-25</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">FIND-2024-002</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Delayed STR Submission</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-orange-100 text-orange-700">Major</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">Reporting</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Resolved</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-20</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>