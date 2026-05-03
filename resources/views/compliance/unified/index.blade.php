<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Compliance View</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Unified Compliance View</h1>
            <p class="mt-1 text-sm text-gray-500">Comprehensive overview of all compliance activities</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Active Alerts</p>
                        <p class="text-3xl font-bold text-orange-600 mt-1">24</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </div>
                </div>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 mt-2 inline-block">View All</a>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Open Cases</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1">8</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                </div>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 mt-2 inline-block">View All</a>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Pending Reviews</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-1">15</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 mt-2 inline-block">View All</a>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">High Risk Customers</p>
                        <p class="text-3xl font-bold text-red-600 mt-1">12</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 mt-2 inline-block">View All</a>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Recent Alerts -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Alerts</h3>
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Velocity Alert</p>
                            <p class="text-xs text-gray-500">Ahmad Razali - RM 45,000</p>
                        </div>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Critical</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Structuring Alert</p>
                            <p class="text-xs text-gray-500">Siti Nurhaliza - 5 transactions</p>
                        </div>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">High</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Sanctions Match</p>
                            <p class="text-xs text-gray-500">Tan Wei Ming - Potential</p>
                        </div>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">High</span>
                    </div>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Pending Tasks</h3>
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 border border-[#e5e5e5] rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Review EDD Request</p>
                            <p class="text-xs text-gray-500">Customer: Lee Mei Ling</p>
                        </div>
                        <span class="text-xs text-gray-500">Due: Today</span>
                    </div>
                    <div class="flex items-center justify-between p-3 border border-[#e5e5e5] rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Approve STR</p>
                            <p class="text-xs text-gray-500">Case: CASE-2024-001</p>
                        </div>
                        <span class="text-xs text-gray-500">Due: Tomorrow</span>
                    </div>
                    <div class="flex items-center justify-between p-3 border border-[#e5e5e5] rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Complete CDD Review</p>
                            <p class="text-xs text-gray-500">Customer: Tan Wei Ming</p>
                        </div>
                        <span class="text-xs text-gray-500">Due: Jan 20</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15 14:30</td>
                        <td class="px-4 py-3 text-sm text-gray-900">John Smith</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Alert Reviewed</td>
                        <td class="px-4 py-3 text-sm text-gray-500">ALT-2024-001 marked as investigating</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15 14:15</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Jane Doe</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Case Created</td>
                        <td class="px-4 py-3 text-sm text-gray-500">CASE-2024-002 for high-risk review</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15 13:45</td>
                        <td class="px-4 py-3 text-sm text-gray-900">John Smith</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Customer Screened</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Ahmad Razali - Sanctions check</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>