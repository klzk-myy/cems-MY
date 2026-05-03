<x-app-layout title="Month-End Close">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Month-End Close</h1>
                <p class="mt-1 text-sm text-gray-500">Process month-end accounting procedures</p>
            </div>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                Start Month-End
            </button>
        </div>

        <!-- Current Period Info -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-sm text-gray-500">Current Period</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">May 2026 (P05)</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Fiscal Year</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">FY 2026</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Period Status</p>
                    <p class="mt-1">
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">In Progress</span>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Due Date</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">2026-06-05</p>
                </div>
            </div>
        </div>

        <!-- Checklist -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e5e5e5]">
                <h3 class="text-sm font-medium text-gray-900">Month-End Checklist</h3>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Task</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Completed By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Completed At</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">1</td>
                        <td class="px-4 py-3 text-sm">Verify all transactions posted</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Complete</span>
                        </td>
                        <td class="px-4 py-3 text-sm">Admin User</td>
                        <td class="px-4 py-3 text-sm">2026-05-03 09:00</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">2</td>
                        <td class="px-4 py-3 text-sm">Run currency revaluation</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Complete</span>
                        </td>
                        <td class="px-4 py-3 text-sm">Admin User</td>
                        <td class="px-4 py-3 text-sm">2026-05-03 09:05</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">3</td>
                        <td class="px-4 py-3 text-sm">Reconcile bank accounts</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span>
                        </td>
                        <td class="px-4 py-3 text-sm">-</td>
                        <td class="px-4 py-3 text-sm">-</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">Start</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">4</td>
                        <td class="px-4 py-3 text-sm">Generate month-end reports</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span>
                        </td>
                        <td class="px-4 py-3 text-sm">-</td>
                        <td class="px-4 py-3 text-sm">-</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">Start</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">5</td>
                        <td class="px-4 py-3 text-sm">Approve and post journal entries</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span>
                        </td>
                        <td class="px-4 py-3 text-sm">-</td>
                        <td class="px-4 py-3 text-sm">-</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">Start</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">6</td>
                        <td class="px-4 py-3 text-sm">Close accounting period</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-500">Waiting</span>
                        </td>
                        <td class="px-4 py-3 text-sm">-</td>
                        <td class="px-4 py-3 text-sm">-</td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800" disabled>Start</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">Completed Tasks</p>
                <p class="mt-1 text-2xl font-semibold text-green-600">2 of 6</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">Pending Tasks</p>
                <p class="mt-1 text-2xl font-semibold text-yellow-600">3 of 6</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">Waiting Tasks</p>
                <p class="mt-1 text-2xl font-semibold text-gray-500">1 of 6</p>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-4">Recent Activity</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-[#e5e5e5]">
                    <div>
                        <p class="text-sm text-gray-900">Currency revaluation completed</p>
                        <p class="text-xs text-gray-500">Admin User</p>
                    </div>
                    <p class="text-sm text-gray-500">2026-05-03 09:05</p>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-[#e5e5e5]">
                    <div>
                        <p class="text-sm text-gray-900">Transaction verification completed</p>
                        <p class="text-xs text-gray-500">Admin User</p>
                    </div>
                    <p class="text-sm text-gray-500">2026-05-03 09:00</p>
                </div>
                <div class="flex items-center justify-between py-2">
                    <div>
                        <p class="text-sm text-gray-900">Month-end process initiated</p>
                        <p class="text-xs text-gray-500">Admin User</p>
                    </div>
                    <p class="text-sm text-gray-500">2026-05-03 08:55</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
