<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTOS Reports</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">CTOS Reports</h1>
                    <p class="mt-1 text-sm text-gray-500">Cash Transaction Summary Reports for BNM</p>
                </div>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Generate Report
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-wrap gap-4">
                <input type="date" class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <input type="date" class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending Approval</option>
                    <option value="submitted">Submitted</option>
                    <option value="accepted">Accepted</option>
                </select>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Filter
                </button>
            </div>
        </div>

        <!-- CTOS Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Report ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Transactions</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">CTOS-2024-01</td>
                        <td class="px-4 py-3 text-sm text-gray-900">January 2024</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Kuala Lumpur</td>
                        <td class="px-4 py-3 text-sm text-gray-900">156</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 4,250,000</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Submitted</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-02-05</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">CTOS-2024-02</td>
                        <td class="px-4 py-3 text-sm text-gray-900">January 2024</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Penang</td>
                        <td class="px-4 py-3 text-sm text-gray-900">89</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 2,100,000</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">-</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>