<x-app-layout title="Sanctions">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Sanctions Screening</h1>
            <p class="mt-1 text-sm text-gray-500">Real-time sanctions list screening</p>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Total Screened</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">1,245</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase">Matches Found</p>
                        <p class="text-3xl font-bold text-red-600 mt-1">3</p>
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
                        <p class="text-xs font-medium text-gray-500 uppercase">Potential Matches</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-1">12</p>
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
                        <p class="text-xs font-medium text-gray-500 uppercase">Clear</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">1,230</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Screening Form -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">New Screening</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Customer Name</label>
                    <input type="text" placeholder="Enter full name" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">ID Number</label>
                    <input type="text" placeholder="Enter ID number" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div class="flex items-end">
                    <button class="w-full px-4 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Screen Customer
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Matches -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Screening Results</h3>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Screened At</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Result</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Match Details</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Ahmad Razali</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15 14:30</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Match</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">John Doe (OFAC) - 92% match</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">Review</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Siti Nurhaliza</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15 14:25</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Potential</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">Jane Smith (BNM) - 75% match</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">Review</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Tan Wei Ming</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15 14:20</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Clear</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">No matches found</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>