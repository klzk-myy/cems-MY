<x-app-layout title="Risk Trends">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Risk Trends</h1>
            <p class="mt-1 text-sm text-gray-500">Historical risk metrics and analysis</p>
        </div>

        <!-- Time Range Filter -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="180">Last 6 Months</option>
                    <option value="365">Last Year</option>
                </select>
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Branches</option>
                    <option value="kl">Kuala Lumpur</option>
                    <option value="penang">Penang</option>
                    <option value="johor">Johor</option>
                </select>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Apply Filter
                </button>
            </div>
        </div>

        <!-- Trend Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <x-chart-trend
                title="High Risk Customer Trend"
                :labels="$highRiskTrend['labels']"
                :values="$highRiskTrend['values']"
                color="red"
            />

            <x-chart-trend
                title="Alert Volume Trend"
                :labels="$alertVolumeTrend['labels']"
                :values="$alertVolumeTrend['values']"
                color="yellow"
            />
        </div>

        <!-- Risk Score Distribution Over Time -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk Score Distribution Over Time</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">High Risk</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Medium Risk</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Low Risk</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">January 2024</td>
                            <td class="px-4 py-3 text-sm text-red-600 font-medium">12</td>
                            <td class="px-4 py-3 text-sm text-yellow-600">28</td>
                            <td class="px-4 py-3 text-sm text-green-600">156</td>
                            <td class="px-4 py-3 text-sm text-gray-900">196</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">December 2023</td>
                            <td class="px-4 py-3 text-sm text-red-600 font-medium">8</td>
                            <td class="px-4 py-3 text-sm text-yellow-600">24</td>
                            <td class="px-4 py-3 text-sm text-green-600">148</td>
                            <td class="px-4 py-3 text-sm text-gray-900">180</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">November 2023</td>
                            <td class="px-4 py-3 text-sm text-red-600 font-medium">6</td>
                            <td class="px-4 py-3 text-sm text-yellow-600">22</td>
                            <td class="px-4 py-3 text-sm text-green-600">142</td>
                            <td class="px-4 py-3 text-sm text-gray-900">170</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Key Insights -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Key Insights</h3>
            <div class="space-y-3">
                <div class="flex items-start gap-3 p-3 bg-red-50 rounded-lg">
                    <svg class="w-5 h-5 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-red-900">High Risk Customers Increased</p>
                        <p class="text-xs text-red-700">High risk customer count increased by 50% compared to last month</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 bg-yellow-50 rounded-lg">
                    <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-900">Alert Volume Up</p>
                        <p class="text-xs text-yellow-700">Total alerts increased by 41% month-over-month</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 bg-green-50 rounded-lg">
                    <svg class="w-5 h-5 text-green-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-green-900">EDD Completion Rate Good</p>
                        <p class="text-xs text-green-700">95% of EDD reviews completed within SLA</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
