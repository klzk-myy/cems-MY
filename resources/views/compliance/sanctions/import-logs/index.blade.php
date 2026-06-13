<x-app-layout title="Sanctions Import Logs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sanctions Import Logs</h1>
                    <p class="mt-1 text-sm text-gray-500">History of sanctions list imports</p>
                </div>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Import List
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Sources</option>
                    <option value="ofac">OFAC SDN</option>
                    <option value="un">UN Security Council</option>
                    <option value="eu">EU Sanctions List</option>
                    <option value="bnm">BNM List</option>
                </select>
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="partial">Partial</option>
                </select>
                <input type="date" class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Filter
                </button>
            </div>
        </div>

        <!-- Import Logs Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Import ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Imported At</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Records</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Updated</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Removed</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">IMP-2024-001</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">OFAC</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15 02:00:00</td>
                        <td class="px-4 py-3 text-sm text-gray-900">5,432</td>
                        <td class="px-4 py-3 text-sm text-green-600">12</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">45</td>
                        <td class="px-4 py-3 text-sm text-red-600">3</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">IMP-2024-002</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-700">UN</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15 02:30:00</td>
                        <td class="px-4 py-3 text-sm text-gray-900">1,205</td>
                        <td class="px-4 py-3 text-sm text-green-600">5</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">12</td>
                        <td class="px-4 py-3 text-sm text-red-600">1</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">IMP-2024-003</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">BNM</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-14 02:00:00</td>
                        <td class="px-4 py-3 text-sm text-gray-900">89</td>
                        <td class="px-4 py-3 text-sm text-green-600">2</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">0</td>
                        <td class="px-4 py-3 text-sm text-red-600">0</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">IMP-2024-004</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">OFAC</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-10 02:00:00</td>
                        <td class="px-4 py-3 text-sm text-gray-900">-</td>
                        <td class="px-4 py-3 text-sm text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Failed</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View Log</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
