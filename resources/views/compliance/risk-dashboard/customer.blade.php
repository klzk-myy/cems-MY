<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Risk Dashboard</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Customer Risk Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Risk assessment for: Ahmad Razali</p>
        </div>

        <!-- Customer Info -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Customer ID</label>
                    <p class="text-sm text-gray-900">CUST-001</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Risk Level</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">High</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">CDD Level</label>
                    <p class="text-sm text-gray-900">Enhanced</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Account Status</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Under Review</span>
                </div>
            </div>
        </div>

        <!-- Risk Factors -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk Factors</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-500">Transaction Velocity</span>
                        <span class="text-sm font-medium text-red-700">High Risk</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-red-600 h-2 rounded-full" style="width: 85%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">RM 125,000 in last 30 days</p>
                </div>
                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-500">Structuring Risk</span>
                        <span class="text-sm font-medium text-red-700">High Risk</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-red-600 h-2 rounded-full" style="width: 72%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">5 transactions near threshold</p>
                </div>
                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-500">PEP Exposure</span>
                        <span class="text-sm font-medium text-yellow-700">Medium</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-500 h-2 rounded-full" style="width: 50%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Indirect connection detected</p>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Transactions</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Risk Flag</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Buy USD</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 28,000</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Alert</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-12</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Sell USD</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 35,000</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Alert</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-10</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Buy EUR</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 25,000</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Review</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Request EDD
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Lock Account
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    View Full Profile
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Export Report
                </button>
            </div>
        </div>
    </div>
</body>
</html>