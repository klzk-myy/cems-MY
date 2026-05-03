<x-app-layout title="Alert Details - ALT-2024-001">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Alert Details</h1>
                    <p class="mt-1 text-sm text-gray-500">ALT-2024-001</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Alert Details Card -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Alert Type</label>
                    <p class="text-sm text-gray-900">Velocity Alert</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Severity</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Critical</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Status</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Reviewing</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Customer</label>
                    <p class="text-sm text-gray-900">Ahmad Razali</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Created At</label>
                    <p class="text-sm text-gray-900">2024-01-15 09:30:00</p>
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
            <p class="text-sm text-gray-600">Multiple transactions approaching reporting threshold within a 7-day period. Customer has conducted 5 transactions totaling RM 45,000 which may indicate structuring behavior to avoid the RM 50,000 STR threshold.</p>
        </div>

        <!-- Transaction History -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Related Transactions</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">TXN-2024-001</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-10</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Buy USD</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 8,000</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">TXN-2024-002</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-12</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Sell USD</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 12,000</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">TXN-2024-003</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-14</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Buy EUR</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 25,000</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Resolve Alert
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Escalate
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Dismiss
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Assign to Me
                </button>
            </div>
        </div>
    </div>
</x-app-layout>