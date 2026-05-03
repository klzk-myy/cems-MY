<x-app-layout title="Fiscal Years">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Fiscal Years</h1>
                <p class="mt-1 text-sm text-gray-500">Manage accounting fiscal years and periods</p>
            </div>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                + Create Fiscal Year
            </button>
        </div>

        <!-- Active Fiscal Year -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Active Fiscal Year</h3>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">FY 2026</p>
                    <p class="mt-1 text-sm text-gray-500">January 1, 2026 - December 31, 2026</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                    <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Close Year</button>
                </div>
            </div>
        </div>

        <!-- Fiscal Years Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fiscal Year</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Periods</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">FY 2026</td>
                        <td class="px-4 py-3 text-sm">2026-01-01</td>
                        <td class="px-4 py-3 text-sm">2026-12-31</td>
                        <td class="px-4 py-3 text-center text-sm">12</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">FY 2025</td>
                        <td class="px-4 py-3 text-sm">2025-01-01</td>
                        <td class="px-4 py-3 text-sm">2025-12-31</td>
                        <td class="px-4 py-3 text-center text-sm">12</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">FY 2024</td>
                        <td class="px-4 py-3 text-sm">2024-01-01</td>
                        <td class="px-4 py-3 text-sm">2024-12-31</td>
                        <td class="px-4 py-3 text-center text-sm">12</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Periods Summary -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e5e5e5]">
                <h3 class="text-sm font-medium text-gray-900">Current Periods - FY 2026</h3>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">P01</td>
                        <td class="px-4 py-3 text-sm">January</td>
                        <td class="px-4 py-3 text-sm">2026-01-01</td>
                        <td class="px-4 py-3 text-sm">2026-01-31</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">P02</td>
                        <td class="px-4 py-3 text-sm">February</td>
                        <td class="px-4 py-3 text-sm">2026-02-01</td>
                        <td class="px-4 py-3 text-sm">2026-02-28</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">P03</td>
                        <td class="px-4 py-3 text-sm">March</td>
                        <td class="px-4 py-3 text-sm">2026-03-01</td>
                        <td class="px-4 py-3 text-sm">2026-03-31</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Closed</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">April</td>
                        <td class="px-4 py-3 text-sm">2026-04-01</td>
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">Close</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">P05</td>
                        <td class="px-4 py-3 text-sm">May</td>
                        <td class="px-4 py-3 text-sm">2026-05-01</td>
                        <td class="px-4 py-3 text-sm">2026-05-31</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Current</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">View</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
