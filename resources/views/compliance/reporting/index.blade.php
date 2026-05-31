<x-app-layout title="Compliance Reporting">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Compliance Reporting</h1>
            <p class="mt-1 text-sm text-gray-500">Generate and manage BNM compliance reports</p>
        </div>

        <!-- Report Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">CTOS</h3>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Monthly</span>
                </div>
                <p class="text-sm text-gray-500 mb-4">Cash Transaction Summary Report for transactions >= RM 25,000</p>
                <button class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Generate
                </button>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">STR</h3>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">As Required</span>
                </div>
                <p class="text-sm text-gray-500 mb-4">Suspicious Transaction Report for unusual activities</p>
                <button class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Generate
                </button>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">LMCA</h3>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Monthly</span>
                </div>
                <p class="text-sm text-gray-500 mb-4">Monthly Large Cash Amount Report</p>
                <button class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Generate
                </button>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Reports</h3>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Report</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">CTOS - January 2024</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Submitted</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">LCTR - December 2023</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2023-12</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Submitted</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">LMCA - December 2023</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2023-12</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Submitted</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>