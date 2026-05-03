<x-app-layout title="Findings">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Finding Details</h1>
                    <p class="mt-1 text-sm text-gray-500">FIND-2024-001</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Finding Overview -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Title</label>
                    <p class="text-sm text-gray-900">Incomplete CDD Documentation</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Severity</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Critical</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Category</label>
                    <p class="text-sm text-gray-900">Documentation</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Status</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">In Progress</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Due Date</label>
                    <p class="text-sm text-gray-900">2024-01-25</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Assigned To</label>
                    <p class="text-sm text-gray-900">John Smith</p>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Description</h3>
            <p class="text-sm text-gray-600">During the quarterly compliance audit, it was identified that customer ID-12345 has incomplete CDD documentation. The following documents are missing:</p>
            <ul class="list-disc list-inside mt-2 text-sm text-gray-600">
                <li>Proof of address (older than 3 months)</li>
                <li>Source of funds declaration</li>
                <li>Beneficial ownership declaration</li>
            </ul>
        </div>

        <!-- Remediation Plan -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Remediation Plan</h3>
            <div class="space-y-4">
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-green-500"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Request missing documents from customer</p>
                        <p class="text-xs text-gray-500">Due: 2024-01-20 | Status: Completed</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-yellow-500"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Review and verify received documents</p>
                        <p class="text-xs text-gray-500">Due: 2024-01-22 | Status: In Progress</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-gray-300"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Update CDD record in system</p>
                        <p class="text-xs text-gray-500">Due: 2024-01-25 | Status: Pending</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Update Status
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Add Note
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Assign
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-green-50 border border-green-200 text-green-700 hover:bg-green-100">
                    Mark Resolved
                </button>
            </div>
        </div>
    </div>
</x-app-layout>