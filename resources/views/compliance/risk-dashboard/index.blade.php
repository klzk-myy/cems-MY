<x-app-layout title="Risk Dashboard">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Risk Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Customer risk overview and analytics</p>
        </div>

        <!-- Risk Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">High Risk</p>
                        <p class="text-3xl font-bold text-red-600 mt-1">12</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Medium Risk</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-1">28</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Low Risk</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">156</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">PEP Customers</p>
                        <p class="text-3xl font-bold text-purple-600 mt-1">8</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk Distribution</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">High Risk</span>
                        <div class="flex items-center gap-3">
                            <div class="w-48 bg-gray-200 rounded-full h-3">
                                <div class="bg-red-600 h-3 rounded-full" style="width: 6%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">6%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Medium Risk</span>
                        <div class="flex items-center gap-3">
                            <div class="w-48 bg-gray-200 rounded-full h-3">
                                <div class="bg-yellow-500 h-3 rounded-full" style="width: 14%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">14%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Low Risk</span>
                        <div class="flex items-center gap-3">
                            <div class="w-48 bg-gray-200 rounded-full h-3">
                                <div class="bg-green-600 h-3 rounded-full" style="width: 80%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">80%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">High Risk Customers</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Ahmad Razali</p>
                            <p class="text-xs text-gray-500">CUST-001</p>
                        </div>
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View</a>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Siti Nurhaliza</p>
                            <p class="text-xs text-gray-500">CUST-042</p>
                        </div>
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View</a>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Tan Wei Ming</p>
                            <p class="text-xs text-gray-500">CUST-108</p>
                        </div>
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Risk Changes -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Risk Score Changes</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Previous Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Change Reason</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Ahmad Razali</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">Medium</td>
                        <td class="px-4 py-3 text-sm text-red-600">High</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Velocity alert triggered</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Lee Mei Ling</td>
                        <td class="px-4 py-3 text-sm text-green-600">Low</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">Medium</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Transaction pattern change</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-14</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>