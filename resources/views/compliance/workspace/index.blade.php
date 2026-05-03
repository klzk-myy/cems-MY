<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance Workspace</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Compliance Workspace</h1>
            <p class="mt-1 text-sm text-gray-500">Your personal compliance workspace</p>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button class="px-4 py-3 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Screen Customer
                </button>
                <button class="px-4 py-3 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Create Alert
                </button>
                <button class="px-4 py-3 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    New Case
                </button>
                <button class="px-4 py-3 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Generate Report
                </button>
            </div>
        </div>

        <!-- My Tasks -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">My Tasks</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-3 border border-[#e5e5e5] rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Review EDD request for Lee Mei Ling</p>
                            <p class="text-xs text-gray-500">Due: Today</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 border border-[#e5e5e5] rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Approve STR for CASE-2024-001</p>
                            <p class="text-xs text-gray-500">Due: Tomorrow</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 border border-[#e5e5e5] rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Complete CDD review for Tan Wei Ming</p>
                            <p class="text-xs text-gray-500">Due: Jan 20, 2024</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 border border-[#e5e5e5] rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Respond to BNM inquiry</p>
                            <p class="text-xs text-gray-500">Due: Jan 18, 2024</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Assigned Cases -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Assigned Cases</h3>
                    <span class="text-sm text-gray-500">4 cases</span>
                </div>
                <div class="space-y-3">
                    <a href="#" class="block p-3 border border-[#e5e5e5] rounded-lg hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">CASE-2024-001</p>
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">In Progress</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Suspicious Transaction Pattern</p>
                    </a>
                    <a href="#" class="block p-3 border border-[#e5e5e5] rounded-lg hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">CASE-2024-003</p>
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending Review</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">High-Risk Customer Review</p>
                    </a>
                    <a href="#" class="block p-3 border border-[#e5e5e5] rounded-lg hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">CASE-2024-004</p>
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Resolved</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">EDD Completion Review</p>
                    </a>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">My Performance (This Month)</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-900">28</p>
                    <p class="text-xs text-gray-500 mt-1">Alerts Reviewed</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-900">12</p>
                    <p class="text-xs text-gray-500 mt-1">Cases Resolved</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-900">45</p>
                    <p class="text-xs text-gray-500 mt-1">Customers Screened</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-green-600">98%</p>
                    <p class="text-xs text-gray-500 mt-1">SLA Compliance</p>
                </div>
            </div>
        </div>

        <!-- Recent Work -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Work</h3>
            <div class="space-y-4">
                <div class="flex items-center gap-4 p-3 border border-[#e5e5e5] rounded-lg">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Resolved Alert ALT-2024-015</p>
                        <p class="text-xs text-gray-500">Structuring alert for Siti Nurhaliza</p>
                    </div>
                    <span class="text-xs text-gray-400">2 hours ago</span>
                </div>
                <div class="flex items-center gap-4 p-3 border border-[#e5e5e5] rounded-lg">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Created Report RPT-2024-003</p>
                        <p class="text-xs text-gray-500">CTOS report for January 2024</p>
                    </div>
                    <span class="text-xs text-gray-400">5 hours ago</span>
                </div>
                <div class="flex items-center gap-4 p-3 border border-[#e5e5e5] rounded-lg">
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Completed EDD for Ahmad Razali</p>
                        <p class="text-xs text-gray-500">Enhanced due diligence review</p>
                    </div>
                    <span class="text-xs text-gray-400">Yesterday</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>