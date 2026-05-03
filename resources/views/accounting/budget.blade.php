<x-layouts.app>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Budget Management</h1>
                <p class="mt-1 text-sm text-gray-500">Manage annual budgets and variances</p>
            </div>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                + Create Budget
            </button>
        </div>

        <!-- Budget Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">Total Budget</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">RM 1,250,000.00</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">YTD Actual</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">RM 875,420.50</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">YTD Variance</p>
                <p class="mt-1 text-2xl font-semibold text-green-600">RM 374,579.50</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">% Used</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">70.0%</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="2026">Fiscal Year 2026</option>
                    <option value="2025">Fiscal Year 2025</option>
                </select>
                <select class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Departments</option>
                    <option value="operations">Operations</option>
                    <option value="compliance">Compliance</option>
                    <option value="finance">Finance</option>
                </select>
                <input type="text" placeholder="Search accounts..." class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg md:w-64">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Filter</button>
            </div>
        </div>

        <!-- Budget Lines Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Annual Budget</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">YTD Actual</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">YTD Budget</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Variance</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Used</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono">5100-001</td>
                        <td class="px-4 py-3 text-sm">Currency Exchange Revenue</td>
                        <td class="px-4 py-3 text-sm text-right">500,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">350,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">291,666.67</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+58,333.33</td>
                        <td class="px-4 py-3 text-center"><span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">70%</span></td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">Edit</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono">6100-001</td>
                        <td class="px-4 py-3 text-sm">Staff Salaries</td>
                        <td class="px-4 py-3 text-sm text-right">400,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">280,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">233,333.33</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+46,666.67</td>
                        <td class="px-4 py-3 text-center"><span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">70%</span></td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">Edit</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono">6200-001</td>
                        <td class="px-4 py-3 text-sm">Office Rent</td>
                        <td class="px-4 py-3 text-sm text-right">150,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">107,500.00</td>
                        <td class="px-4 py-3 text-sm text-right">87,500.00</td>
                        <td class="px-4 py-3 text-sm text-right text-red-600">-20,000.00</td>
                        <td class="px-4 py-3 text-center"><span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">72%</span></td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">Edit</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono">6300-001</td>
                        <td class="px-4 py-3 text-sm">Compliance Costs</td>
                        <td class="px-4 py-3 text-sm text-right">200,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">137,920.50</td>
                        <td class="px-4 py-3 text-sm text-right">116,666.67</td>
                        <td class="px-4 py-3 text-sm text-right text-green-600">+21,253.83</td>
                        <td class="px-4 py-3 text-center"><span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">69%</span></td>
                        <td class="px-4 py-3 text-center">
                            <button class="text-blue-600 hover:text-blue-800">Edit</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Showing 1-4 of 4 accounts</p>
            <div class="flex gap-2">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] disabled:opacity-50" disabled>Previous</button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] disabled:opacity-50" disabled>Next</button>
            </div>
        </div>
    </div>
</x-layouts.app>
